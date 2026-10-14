<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Groups\FileRoot;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\Files\FileApplier;
use App\Services\Migration\Import\Files\FilesJournal;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\SelectedGroups;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageReader;

/**
 * Stage 13. Puts the package's files on the disk: only those under a folder of a group being imported, each one through
 * the FileApplier (links, scripts and anything outside the folders are refused and counted, never fatal).
 *
 * The cursor is the index shard and the line inside it, saved every few hundred files and at the end of every budgeted
 * step. Work BEFORE the cursor is done; a process that dies after the cursor was saved redoes at most a few hundred files,
 * and a file that is already there with the right size and hash is counted and left alone, so redoing is cheap and safe.
 * Files that were on the site and are not in the package stay; the report says how many.
 *
 * Not final: a test replaces `applier()` to fail at a chosen file.
 */
class FilesStage implements ImportStage
{
    /** Save the cursor after this many files, or this many bytes written, whichever comes first. */
    private const SAVE_EVERY_FILES = 200;

    private const SAVE_EVERY_BYTES = 64 * 1024 * 1024;

    /** @param  array<string, array{path: string, prefix: string}>  $disks */
    public function __construct(
        private readonly Kdf $kdf,
        private readonly GroupRegistry $registry,
        private readonly ImportMarker $marker,
        private readonly ImportStateStore $store,
        private readonly array $disks,
    ) {}

    public function key(): string
    {
        return 'files';
    }

    public function destructive(): bool
    {
        return true;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('files.done')) {
            return ImportStepResult::done('The files are in place.');
        }

        if (! $this->marker->isActive() || ! $state->flag('seed.done')) {
            throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => 'files', 'why' => 'the data has not been put in place']);
        }

        $roots = $this->registry->fileRootsOf(SelectedGroups::of($state));

        if ($roots === []) {
            $state->package['files'] = $this->report(['written' => 0, 'replaced' => 0, 'same' => 0, 'bytes' => 0, 'rejected' => [], 'samples' => []], 0);
            $state->setFlag('files.done');

            return ImportStepResult::done('No files to put in place.');
        }

        $keys = $context->escrow->get(ImportContext::escrowId($state->id));

        if ($keys === null) {
            return ImportStepResult::needsPassphrase('The key for this package has expired: type the passphrase again.');
        }

        try {
            $reader = PackageReader::openWithKeys((string) $state->package['path'], $keys->keys, $this->kdf);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        }

        try {
            return $this->apply($state, $context, $reader, $roots);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  array<int, FileRoot>  $roots
     */
    private function apply(ImportState $state, ImportContext $context, PackageReader $reader, array $roots): ImportStepResult
    {
        $allowed = array_map(fn ($root): string => $this->disks[$root->disk]['prefix'].'/'.$root->path, $roots);
        $journal = new FilesJournal($this->store, $state->id);
        $applier = $this->applier($allowed, $journal, $state->id);

        $shards = [];

        foreach ($reader->entries() as $entry) {
            if (($entry['kind'] ?? '') === 'files-index') {
                $shards[] = (string) $entry['name'];
            }
        }

        sort($shards);

        $cursor = (array) ($state->stageState['fc'] ?? ['si' => 0, 'li' => 0]);
        $stats = (array) ($state->stageState['fs'] ?? ['written' => 0, 'replaced' => 0, 'same' => 0, 'bytes' => 0, 'rejected' => [], 'samples' => []]);
        $total = max(1, (int) array_sum(array_map(static fn (array $e): int => (int) ($e['rows'] ?? 0), array_filter($reader->entries(), static fn (array $e): bool => ($e['kind'] ?? '') === 'files-index'))));

        $sinceSave = 0;
        $bytesSinceSave = 0;

        while ($cursor['si'] < count($shards)) {
            $shard = new IndexShardFeed(
                $cursor['li'],
                function (array $row) use (&$cursor, &$stats, $applier, $reader, $state, $context, &$sinceSave, &$bytesSinceSave): void {
                    $result = $applier->apply($row, $reader);
                    $this->count($stats, $result, (string) ($row['p'] ?? ''));
                    $cursor['li']++;
                    $sinceSave++;
                    $bytesSinceSave += $result['bytes'];

                    if ($sinceSave >= self::SAVE_EVERY_FILES || $bytesSinceSave >= self::SAVE_EVERY_BYTES || $context->expired()) {
                        $this->save($state, $cursor, $stats);
                        $sinceSave = 0;
                        $bytesSinceSave = 0;

                        if ($context->expired()) {
                            throw new StopApplying;
                        }
                    }
                },
            );

            try {
                $reader->readEntry($shards[$cursor['si']], static function (string $chunk) use ($shard): void {
                    $shard->feed($chunk);
                });
                $shard->finish();
            } catch (StopApplying) {
                return ImportStepResult::progress(min(0.99, $this->filesDone($stats) / $total), 'Putting files in place: '.$this->filesDone($stats).' of '.$total.'.');
            }

            $cursor = ['si' => $cursor['si'] + 1, 'li' => 0];
            $this->save($state, $cursor, $stats);
        }

        $state->package['files'] = $this->report($stats, $this->leftovers($roots, $stats));
        $state->setFlag('files.done');
        $state->stageState = [];

        return ImportStepResult::done($this->filesDone($stats).' file(s) put in place.');
    }

    /**
     * @param  array<int, string>  $allowedRoots
     */
    protected function applier(array $allowedRoots, FilesJournal $journal, string $runId): FileApplier
    {
        return new FileApplier($this->disks, $allowedRoots, $journal, $runId);
    }

    /**
     * @param  array<string, mixed>  $cursor
     * @param  array<string, mixed>  $stats
     */
    private function save(ImportState $state, array $cursor, array $stats): void
    {
        $state->stageState = ['fc' => $cursor, 'fs' => $stats];
        $state->touch();
        $this->store->save($state);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array{outcome: string, bytes: int, reason: string|null}  $result
     */
    private function count(array &$stats, array $result, string $path): void
    {
        switch ($result['outcome']) {
            case FileApplier::WRITTEN:
                $stats['written']++;
                $stats['bytes'] += $result['bytes'];
                break;
            case FileApplier::REPLACED:
                $stats['written']++;
                $stats['replaced']++;
                $stats['bytes'] += $result['bytes'];
                break;
            case FileApplier::SAME:
                $stats['same']++;
                break;
            default:
                $reason = (string) $result['reason'];
                $stats['rejected'][$reason] = ($stats['rejected'][$reason] ?? 0) + 1;

                if (count($stats['samples']) < 20) {
                    $stats['samples'][] = ['path' => $path, 'reason' => $reason];
                }
        }
    }

    /** @param  array<string, mixed>  $stats */
    private function filesDone(array $stats): int
    {
        return (int) $stats['written'] + (int) $stats['same'] + (int) array_sum((array) $stats['rejected']);
    }

    /**
     * Files already in the folders of the imported groups that the package does not hold (they stay). Counted up to a cap.
     *
     * @param  array<int, FileRoot>  $roots
     * @param  array<string, mixed>  $stats
     */
    private function leftovers(array $roots, array $stats): int
    {
        $cap = 100_000;
        $count = 0;

        foreach ($roots as $root) {
            $directory = $this->disks[$root->disk]['path'].DIRECTORY_SEPARATOR.$root->path;

            if (! is_dir($directory)) {
                continue;
            }

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && ! str_starts_with($file->getFilename(), '.oeimp-') && ++$count >= $cap) {
                    break 2;
                }
            }
        }

        return max(0, $count - (int) $stats['written'] - (int) $stats['same']);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function report(array $stats, int $leftovers): array
    {
        return [
            'written' => (int) $stats['written'], 'replaced' => (int) $stats['replaced'], 'unchanged' => (int) $stats['same'],
            'bytes' => (int) $stats['bytes'], 'rejected' => (array) $stats['rejected'], 'rejected_sample' => (array) $stats['samples'],
            'leftovers' => $leftovers,
        ];
    }
}

/** Thrown from inside a streamed shard to stop between two files when the time is up. */
final class StopApplying extends \RuntimeException {}

/**
 * Reads one index shard's lines as they arrive, skips the ones the cursor says are done, and hands the rest to a callback
 * one decoded record at a time.
 */
final class IndexShardFeed
{
    private string $buffer = '';

    private int $seen = 0;

    /** @param  \Closure(array<string, mixed>): void  $row */
    public function __construct(private readonly int $skip, private readonly \Closure $row) {}

    public function feed(string $chunk): void
    {
        $this->buffer .= $chunk;

        while (($newline = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $newline);
            $this->buffer = substr($this->buffer, $newline + 1);

            $this->line($line);
        }
    }

    public function finish(): void
    {
        if ($this->buffer !== '') {
            $this->line($this->buffer);
            $this->buffer = '';
        }
    }

    private function line(string $line): void
    {
        if ($line === '') {
            return;
        }

        $this->seen++;

        if ($this->seen <= $this->skip) {
            return;
        }

        try {
            $row = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'file index']);
        }

        if (! is_array($row)) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'file index']);
        }

        ($this->row)($row);
    }
}
