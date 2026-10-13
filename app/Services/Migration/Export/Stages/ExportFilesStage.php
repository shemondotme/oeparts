<?php

namespace App\Services\Migration\Export\Stages;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Security\PackagePathPolicy;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\StageStepResult;
use App\Services\Migration\Export\AllowlistWalker;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\FileEntry;
use App\Services\Migration\Export\VolumeWriter;
use App\Services\Migration\Groups\FileRoot;
use Illuminate\Support\Facades\Storage;

/**
 * Copies the user files of the selected groups into compressed volumes, in two phases, resumably.
 *
 * SCAN: the allowlist walker lists every file that may travel into a work file (`entries.ndjson`), one
 * line each, never into a PHP array. Archive reads that file back by byte offset, so a million files cost
 * a million short lines on disk, not memory, and are not re-decoded every step.
 *
 * ARCHIVE: each listed file is compressed block by block into the open volume (about 512 MB each) and one
 * index line is written for it: disk, disk-relative path, size, mtime, SHA-256, volume number and the
 * `[offset, length, raw length]` of each block. Index lines are rotated into shards of 50,000 rows
 * (`files/index.NNN.ndjson.gz`) so no single piece is huge.
 *
 * RESUME is by truncation, not by checkpointing every file: the checkpoint records how many bytes of the
 * work file, the open volume and the open index shard are committed, and every step first cuts each back to
 * those lengths. A kill in the middle of a file therefore leaves no duplicate and no torn record, and the
 * checkpoint is written once per step (about a second of work), not once per file.
 *
 * A file that vanished between the scan and its turn, or cannot be read, is recorded as skipped. One that
 * grew while it was read is stored as the bytes actually copied, with the hash of those bytes.
 */
class ExportFilesStage implements BackupStage
{
    /** Index lines per shard unless configured otherwise (tests use a handful). */
    public const INDEX_SHARD_ROWS = 50_000;

    /** @param  array<string, array{path: string, prefix: string}>  $disks */
    public function __construct(private readonly array $disks) {}

    public function key(): string
    {
        return BackupChunk::TYPE_FILES;
    }

    public function step(BackupRun $run, array $state): StageStepResult
    {
        $plan = ExportPlan::fromArray((array) ($run->meta['export_plan'] ?? throw new \RuntimeException('This export run has no plan.')));

        $roots = array_map(FileRoot::parse(...), $plan->fileRoots);
        $walker = new AllowlistWalker(new PackagePathPolicy, $this->disks);
        $state = $state === [] ? $this->initial($walker, $roots) : $state;
        $parts = [];
        $deadline = microtime(true) + max(0.0, (float) config('migration.export.batch_seconds', 10));

        if ($state['phase'] === 'scan') {
            $state = $this->scan($run, $walker, $state, $deadline);

            if ($state['phase'] === 'scan') {
                return StageStepResult::progress($state, null, 'Looking for files: '.$state['counts']['found'].' found', 0.25 * $this->scanFraction($state));
            }
        }

        $state = $this->archive($run, $state, $deadline, $parts);

        if ($state['phase'] === 'archive') {
            $fraction = 0.25 + 0.75 * ($state['counts']['found_bytes'] > 0 ? min(1.0, $state['counts']['archived_bytes'] / $state['counts']['found_bytes']) : 1.0);

            return StageStepResult::progress($state, array_pop($parts), 'Copying files: '.$state['counts']['archived'].' of '.$state['counts']['found'], min(0.99, $fraction), $parts);
        }

        $this->recordReport($run, $state);

        return new StageStepResult(done: true, state: [], part: array_pop($parts), message: 'Files copied: '.$state['counts']['archived'].'.', fraction: 1.0, parts: $parts);
    }

    // ---- scan ---------------------------------------------------------------------------------------

    /**
     * @param  array<int, FileRoot>  $roots
     * @return array<string, mixed>
     */
    private function initial(AllowlistWalker $walker, array $roots): array
    {
        return [
            'phase' => 'scan',
            'walk' => $walker->start($roots),
            'entries_bytes' => 0,
            'offset' => 0,
            'vol' => ['n' => 0, 'bytes' => 0, 'files' => 0],
            'shard' => ['n' => 0, 'bytes' => 0, 'rows' => 0],
            'skipped_listed' => 0,
            'counts' => ['found' => 0, 'found_bytes' => 0, 'archived' => 0, 'archived_bytes' => 0, 'vanished' => 0, 'volumes' => 0, 'shards' => 0],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function scan(BackupRun $run, AllowlistWalker $walker, array $state, float $deadline): array
    {
        $path = $this->absolute($run, 'files/entries.ndjson');
        $this->ensureDirectory(dirname($path));

        $handle = @fopen($path, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open the file list {$path}.");
        }

        if ((int) (fstat($handle)['size'] ?? 0) < $state['entries_bytes']) {
            fclose($handle);

            throw new \RuntimeException('The file list is shorter than its checkpoint says: the staging was damaged.');
        }

        ftruncate($handle, $state['entries_bytes']);
        fseek($handle, $state['entries_bytes']);

        $cursor = $state['walk'];
        $written = 0;

        try {
            do {
                $cursor = $walker->step($cursor, 500, function (FileEntry $entry) use ($handle, &$written): void {
                    $line = json_encode(['d' => $entry->disk, 'p' => $entry->path, 's' => $entry->size, 'm' => $entry->mtime], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";

                    if (fwrite($handle, $line) !== strlen($line)) {
                        throw new \RuntimeException('Short write to the file list: the disk may be full.');
                    }

                    $written += strlen($line);
                });
            } while (! $cursor['done'] && microtime(true) < $deadline);
        } finally {
            fflush($handle);
            fclose($handle);
        }

        $state['entries_bytes'] += $written;
        $state['counts']['found'] = $cursor['stats']['files'];
        $state['counts']['found_bytes'] = $cursor['stats']['bytes'];

        // The skipped sample lives in a file, not in the checkpoint: a hostile tree would otherwise grow it.
        $state['skipped_listed'] = $this->drainSkippedSample($run, $cursor, $state['skipped_listed']);
        $state['skipped_counts'] = $cursor['skipped_counts'];

        if ($cursor['done']) {
            $state['phase'] = 'archive';
            $state['walk'] = ['done' => true];
        } else {
            $state['walk'] = $cursor;
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $cursor  the walker cursor; its sample is emptied after it has been written out
     */
    private function drainSkippedSample(BackupRun $run, array &$cursor, int $alreadyListed): int
    {
        $room = AllowlistWalker::SKIPPED_SAMPLE - $alreadyListed;

        if ($cursor['skipped_sample'] !== [] && $room > 0) {
            $lines = '';
            foreach (array_slice($cursor['skipped_sample'], 0, $room) as $item) {
                $lines .= json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
                $alreadyListed++;
            }

            file_put_contents($this->absolute($run, 'files/skipped.ndjson'), $lines, FILE_APPEND);
        }

        $cursor['skipped_sample'] = [];

        return $alreadyListed;
    }

    /** @param  array<string, mixed>  $state */
    private function scanFraction(array $state): float
    {
        // The total is unknown until the walk ends, so show gentle progress that never reaches the end.
        return min(0.95, $state['counts']['found'] / ($state['counts']['found'] + 5000));
    }

    // ---- archive -------------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $state
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<string, mixed>
     */
    private function archive(BackupRun $run, array $state, float $deadline, array &$parts): array
    {
        $list = @fopen($this->absolute($run, 'files/entries.ndjson'), 'r+b');
        if ($list === false) {
            // No file list at all means the scan found nothing to write (not even an empty list).
            return $this->finish($run, $state, $parts);
        }

        // The list ends where the scan's last COMMITTED step ended; anything after that is a torn append.
        ftruncate($list, $state['entries_bytes']);
        fseek($list, $state['offset']);
        $volume = VolumeWriter::open($this->absolute($run, $this->volumePath($run, $state['vol']['n'])), $state['vol']['bytes']);
        $shardPath = $this->absolute($run, $this->shardPath($run, $state['shard']['n'], false));
        $this->ensureDirectory(dirname($shardPath));
        $shard = $this->openForAppend($shardPath, $state['shard']['bytes']);

        $batch = max(1, (int) config('migration.export.batch_files', 256));
        $handled = 0;
        $volumeCap = max(1, (int) config('migration.export.volume_bytes', 512 * 1024 * 1024));

        try {
            while (($line = fgets($list)) !== false) {
                $entry = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
                $state['offset'] += strlen($line);

                $written = $volume->appendFile($this->absoluteOnDisk($entry['d'], $entry['p']));

                if ($written === null) {
                    $state['counts']['vanished']++;
                } else {
                    $row = json_encode([
                        'd' => $entry['d'], 'p' => $entry['p'], 's' => $written['size'], 'm' => $written['mtime'],
                        'h' => $written['sha256'], 'v' => $state['vol']['n'], 'seg' => $written['segments'],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";

                    if (fwrite($shard, $row) !== strlen($row)) {
                        throw new \RuntimeException('Short write to the file index: the disk may be full.');
                    }

                    $state['shard']['bytes'] += strlen($row);
                    $state['shard']['rows']++;
                    $state['vol']['files']++;
                    $state['counts']['archived']++;
                    $state['counts']['archived_bytes'] += $written['size'];
                }

                $handled++;

                if ($state['shard']['rows'] >= max(1, (int) config('migration.export.index_shard_rows', self::INDEX_SHARD_ROWS))) {
                    fclose($shard);
                    $this->collect($parts, $this->sealShard($run, $state['shard']));
                    $state['counts']['shards']++;
                    $state['shard'] = ['n' => $state['shard']['n'] + 1, 'bytes' => 0, 'rows' => 0];
                    $shardPath = $this->absolute($run, $this->shardPath($run, $state['shard']['n'], false));
                    $shard = $this->openForAppend($shardPath, 0);
                }

                if ($volume->bytes() >= $volumeCap) {
                    $state['vol']['bytes'] = $volume->bytes();
                    $volume->close();
                    $this->collect($parts, $this->sealVolume($run, $state['vol']));
                    $state['counts']['volumes']++;
                    $state['vol'] = ['n' => $state['vol']['n'] + 1, 'bytes' => 0, 'files' => 0];
                    $volume = VolumeWriter::open($this->absolute($run, $this->volumePath($run, $state['vol']['n'])), 0);
                }

                if ($handled >= $batch || microtime(true) >= $deadline) {
                    break;
                }
            }

            $exhausted = ($line === false);
        } finally {
            $state['vol']['bytes'] = $volume->bytes();
            $volume->close();
            fflush($shard);
            fclose($shard);
            fclose($list);
        }

        if ($exhausted) {
            return $this->finish($run, $state, $parts);
        }

        return $state;
    }

    /**
     * Close the open volume and shard, if they hold anything, and mark the stage done.
     *
     * @param  array<string, mixed>  $state
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<string, mixed>
     */
    private function finish(BackupRun $run, array $state, array &$parts): array
    {
        if ($state['vol']['files'] > 0) {
            $this->collect($parts, $this->sealVolume($run, $state['vol']));
            $state['counts']['volumes']++;
        } else {
            @unlink($this->absolute($run, $this->volumePath($run, $state['vol']['n'])));
        }

        if ($state['shard']['rows'] > 0) {
            $this->collect($parts, $this->sealShard($run, $state['shard']));
            $state['counts']['shards']++;
        } else {
            @unlink($this->absolute($run, $this->shardPath($run, $state['shard']['n'], false)));
        }

        $state['phase'] = 'done';

        return $state;
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     * @param  array<string, mixed>|null  $part
     */
    private function collect(array &$parts, ?array $part): void
    {
        if ($part !== null) {
            $parts[] = $part;
        }
    }

    /** @param  array{n: int, bytes: int, files: int}  $vol */
    private function sealVolume(BackupRun $run, array $vol): ?array
    {
        $relative = $this->volumePath($run, $vol['n']);

        if ($run->parts()->where('path', $relative)->exists()) {
            return null;
        }

        $absolute = $this->absolute($run, $relative);
        $handle = fopen($absolute, 'c+b');
        ftruncate($handle, $vol['bytes']);
        fclose($handle);

        return [
            'type' => BackupChunk::TYPE_FILES, 'name' => sprintf('files/vol-%04d', $vol['n']), 'disk' => $this->disk(), 'path' => $relative,
            'sha256' => hash_file('sha256', $absolute), 'bytes' => $vol['bytes'], 'rows' => $vol['files'],
            'meta' => ['kind' => 'volume', 'index' => $vol['n'], 'codec' => 'none'],
        ];
    }

    /**
     * Compress the finished index shard and register it. The plain shard is removed.
     *
     * @param  array{n: int, bytes: int, rows: int}  $shard
     */
    private function sealShard(BackupRun $run, array $shard): ?array
    {
        $relative = $this->shardPath($run, $shard['n'], true);

        if ($run->parts()->where('path', $relative)->exists()) {
            return null;
        }

        $plain = $this->absolute($run, $this->shardPath($run, $shard['n'], false));
        $gz = $this->absolute($run, $relative);

        $handle = fopen($plain, 'c+b');
        ftruncate($handle, $shard['bytes']);
        fclose($handle);

        $this->compress($plain, $gz);
        @unlink($plain);

        return [
            'type' => BackupChunk::TYPE_FILES, 'name' => sprintf('files/index.%03d', $shard['n']), 'disk' => $this->disk(), 'path' => $relative,
            'sha256' => hash_file('sha256', $gz), 'bytes' => (int) filesize($gz), 'rows' => $shard['rows'],
            'meta' => ['kind' => 'index', 'index' => $shard['n'], 'codec' => 'gzip', 'raw_len' => $shard['bytes']],
        ];
    }

    private function compress(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = fopen($to, 'wb');

        if ($in === false || $out === false) {
            throw new \RuntimeException('Cannot compress the file index.');
        }

        $context = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);

        while (($chunk = fread($in, 1 << 20)) !== false && $chunk !== '') {
            fwrite($out, deflate_add($context, $chunk, ZLIB_NO_FLUSH));
        }

        fwrite($out, deflate_add($context, '', ZLIB_FINISH));
        fclose($in);
        fclose($out);
    }

    /** @param  array<string, mixed>  $state */
    private function recordReport(BackupRun $run, array $state): void
    {
        $skippedFile = $this->absolute($run, 'files/skipped.ndjson');
        $sample = [];
        if (is_file($skippedFile)) {
            foreach (file($skippedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $sample[] = json_decode($line, true);
            }
        }

        $meta = $run->meta ?? [];
        $meta['export_report'] = array_merge((array) ($meta['export_report'] ?? []), [
            'files' => [
                'archived' => $state['counts']['archived'], 'bytes' => $state['counts']['archived_bytes'], 'volumes' => $state['counts']['volumes'],
                'index_shards' => $state['counts']['shards'], 'vanished' => $state['counts']['vanished'],
                'skipped' => ['counts' => $state['skipped_counts'] ?? [], 'sample' => $sample],
            ],
        ]);
        $run->meta = $meta;

        @unlink($this->absolute($run, 'files/entries.ndjson'));
        @unlink($skippedFile);
    }

    // ---- paths ---------------------------------------------------------------------------------------

    private function disk(): string
    {
        return (string) config('backup.staging_disk', 'local');
    }

    private function volumePath(BackupRun $run, int $n): string
    {
        return sprintf('backups/%d/export/files/vol-%04d.oevol', $run->getKey(), $n);
    }

    private function shardPath(BackupRun $run, int $n, bool $compressed): string
    {
        return sprintf('backups/%d/export/files/index.%03d.ndjson', $run->getKey(), $n).($compressed ? '.gz' : '');
    }

    /** A path relative to the staging root of this run's export (the run id is part of every part path). */
    private function absolute(BackupRun $run, string $relative): string
    {
        $relative = str_starts_with($relative, 'backups/') ? $relative : sprintf('backups/%d/export/%s', $run->getKey(), $relative);

        return Storage::disk($this->disk())->path($relative);
    }

    private function absoluteOnDisk(string $disk, string $path): string
    {
        return rtrim($this->disks[$disk]['path'], '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create the directory {$directory}.");
        }
    }

    /** @return resource */
    private function openForAppend(string $path, int $committedBytes)
    {
        $handle = @fopen($path, 'c+b');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open {$path}.");
        }

        if ((int) (fstat($handle)['size'] ?? 0) < $committedBytes) {
            fclose($handle);

            throw new \RuntimeException("{$path} is shorter than its checkpoint says: the staging was damaged.");
        }

        ftruncate($handle, $committedBytes);
        fseek($handle, $committedBytes);

        return $handle;
    }
}
