<?php

namespace App\Services\Backup\Stages;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\StageStepResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * FileBackupStage (Module 14/21, Chunk 2.3) — the second concrete {@see BackupStage}:
 * a pure-PHP, resumable file backup for the `full` (disaster-recovery) profile.
 * (The `update_safety` profile intentionally omits it — the Update Engine keeps its
 * own directory-rename rollback of core files, so a pre-update backup only needs
 * the DB + env.)
 *
 * Container: files are packed into ≤ config('backup.volume_bytes') (512 MB default)
 * VOLUMES. Each file body is streamed in fixed blocks, every block gzip-compressed
 * and appended to the current volume (flat memory even on multi-GB media); the
 * file's byte offsets are recorded so a single file can be extracted on restore
 * without reading the whole volume. A gzipped file MANIFEST (path → sha256/size/
 * mtime + volume/segment map) is emitted as the final part — it doubles as the
 * hash baseline for the next incremental run.
 *
 * Resumable one-part-per-step (rule #48): init walks the tree once (+ diffs against
 * the baseline for incremental); each subsequent step archives a bounded batch of
 * files, appending to the open volume, and finalises a volume as a part the moment
 * it fills. A killed run resumes from the persisted file cursor.
 */
class FileBackupStage implements BackupStage
{
    /** Streaming read block — bounds per-file memory regardless of file size. */
    private const BLOCK = 1048576; // 1 MB

    public function key(): string
    {
        return BackupChunk::TYPE_FILES;
    }

    public function step(BackupRun $run, array $state): StageStepResult
    {
        if (! isset($state['root'])) {
            $state = $this->initialise($run);
        }

        // Phase 0 — walk the tree + diff against the incremental baseline,
        // time-bounded per step like every other phase (see scanBatch()).
        if (! $state['scan_done']) {
            return $this->scanBatch($run, $state);
        }

        // Phase 1 — archive the changed/new files into volumes.
        if ((int) $state['cursor'] < (int) $state['total']) {
            return $this->archiveBatch($run, $state);
        }

        // Phase 2 — flush a still-open volume that holds files.
        if ($state['vol_has_content']) {
            $part = $this->finaliseVolume($run, (int) $state['vol']);
            $state['counts']['volumes'] = (int) $state['counts']['volumes'] + 1;
            $state['vol']               = (int) $state['vol'] + 1;
            $state['vol_has_content']   = false;

            return StageStepResult::progress($state, $part, 'files: volume '.$part['name'], 0.97);
        }

        // Phase 3 — write the file manifest (hash baseline) and clean up.
        // Always the LAST step: StageStepResult::complete() sets done=true,
        // which makes the engine advance past this stage entirely (resetting
        // its state) — step() is never called again for this run, so there
        // was never a real "already done" case to guard against here.
        $part = $this->writeManifest($run, $state);
        $this->cleanupWorkingFiles($run, $state);

        return StageStepResult::complete($part, 'files: manifest');
    }

    /* ---- Init + walk/diff (time-bounded, resumable) --------------------- */

    /**
     * O(1) setup only — the actual tree walk happens in scanBatch(), chunked
     * like every other phase. This used to walk + diff the ENTIRE tree in one
     * uninterruptible call, which could blow past a shared host's execution
     * time limit on a large install before the chunking engine this class
     * exists for ever got a chance to chunk anything.
     */
    private function initialise(BackupRun $run): array
    {
        $root     = rtrim((string) config('backup.files.root', base_path()), "/\\");
        $excludes = (array) config('backup.files.exclude', []);

        $entriesRel     = 'backups/'.$run->getKey().'/files/_work/entries.ndjson';
        $toArchiveRel   = 'backups/'.$run->getKey().'/files/_work/to_archive.ndjson';
        $filelistRel    = 'backups/'.$run->getKey().'/files/_work/filelist.json';
        $this->resetFile($run, $entriesRel);
        $this->resetFile($run, $toArchiveRel);

        return [
            'root'              => $root,
            'excludes'          => $excludes,
            'entries'           => $entriesRel,
            'to_archive_work'   => $toArchiveRel,
            'filelist'          => $filelistRel,
            'scan_done'         => false,
            'scan_pending'      => [''], // relative dir paths still to visit; '' = root itself
            'scan_seen_count'   => 0,
            'scan_archive_count' => 0,
            'counts'            => ['archived' => 0, 'unchanged' => 0, 'deleted' => 0, 'volumes' => 0, 'file_count' => 0],
            'total'             => 0,
            'cursor'            => 0,
            'vol'               => 0,
            'vol_has_content'   => false,
            'baseline_run_id'   => null,
        ];
    }

    /**
     * Walk one time-bounded slice of the tree (a stack of pending directories
     * — resumable by construction, unlike RecursiveDirectoryIterator, which
     * has no serializable resume position), diffing each file found against
     * the incremental baseline as it's discovered. Checkpointed after every
     * directory via the same immediate-save pattern archiveBatch() uses, so
     * a kill mid-scan resumes from the last fully-processed directory rather
     * than restarting the whole walk.
     */
    private function scanBatch(BackupRun $run, array $state): StageStepResult
    {
        $deadline = microtime(true) + max(0.0, (float) config('backup.files.scan_seconds', 5));

        $meta        = (array) ($run->meta ?? []);
        $incremental = (bool) ($meta['incremental'] ?? config('backup.files.incremental', false));
        // Re-derived every call rather than cached in $state: $state is
        // checkpointed to the DB on every directory, and a large incremental
        // baseline has no business living inside that JSON column.
        $baseline    = $incremental ? $this->loadBaseline($run) : ['run_id' => null, 'map' => []];
        $baselineMap = $baseline['map'];
        $state['baseline_run_id'] = $baseline['run_id'];

        if (! isset($state['baseline_seen'])) {
            $state['baseline_seen'] = [];
        }

        do {
            $dirRel = array_shift($state['scan_pending']);
            $dirAbs = $dirRel === '' ? $state['root'] : $state['root'].'/'.$dirRel;

            if (is_dir($dirAbs) && ! is_link($dirAbs)) {
                foreach (new \DirectoryIterator($dirAbs) as $entry) {
                    if ($entry->isDot()) {
                        continue;
                    }

                    $entryRel = $dirRel === '' ? $entry->getFilename() : $dirRel.'/'.$entry->getFilename();

                    if ($this->isExcluded($entryRel, $state['excludes'])) {
                        continue;
                    }

                    if ($entry->isDir()) {
                        if (! $entry->isLink()) {
                            $state['scan_pending'][] = $entryRel;
                        }

                        continue;
                    }

                    if (! $entry->isFile() || $entry->isLink()) {
                        continue;
                    }

                    $size  = $entry->getSize();
                    $mtime = $entry->getMTime();

                    $state['scan_seen_count']++;
                    $state['baseline_seen'][$entryRel] = true;

                    $prev = $baselineMap[$entryRel] ?? null;
                    if ($prev && (int) ($prev['size'] ?? -1) === (int) $size && (int) ($prev['mtime'] ?? -1) === (int) $mtime) {
                        // Unchanged — reference the baseline's stored bytes (incremental chain).
                        $this->appendEntry($run, $state['entries'], [
                            'path'       => $entryRel,
                            'size'       => (int) $size,
                            'mtime'      => (int) $mtime,
                            'sha256'     => $prev['sha256'] ?? null,
                            'unchanged'  => true,
                            'source_run' => $state['baseline_run_id'],
                            'vol'        => $prev['vol'] ?? null,
                            'segments'   => $prev['segments'] ?? [],
                        ]);
                        $state['counts']['unchanged']++;
                    } else {
                        $this->appendEntry($run, $state['to_archive_work'], ['path' => $entryRel]);
                        $state['scan_archive_count']++;
                    }
                }
            }

            $this->checkpointNow($run, $state);
        } while (! empty($state['scan_pending']) && microtime(true) < $deadline);

        if (! empty($state['scan_pending'])) {
            return StageStepResult::progress($state, null, 'files: scanning ('.$state['scan_seen_count'].' seen)', 0.02);
        }

        // Scan complete — deletions are whatever's left in the baseline that
        // scanning never touched, then materialise the flat archive list
        // archiveBatch() already knows how to read.
        foreach ($baselineMap as $rel => $prev) {
            if (! isset($state['baseline_seen'][$rel])) {
                $this->appendEntry($run, $state['entries'], ['path' => $rel, 'deleted' => true]);
                $state['counts']['deleted']++;
            }
        }
        unset($state['baseline_seen']);

        $state['counts']['file_count'] = $state['scan_seen_count'];
        $state['total']     = $state['scan_archive_count'];
        $state['scan_done'] = true;

        Storage::disk($this->stagingDisk())->put(
            $state['filelist'],
            (string) json_encode($this->readToArchiveList($run, $state['to_archive_work']))
        );

        $this->checkpointNow($run, $state);

        return StageStepResult::progress($state, null, 'files: scan complete ('.$state['total'].' to archive)', 0.05);
    }

    /** Read back the NDJSON list of paths scanBatch() decided need archiving. */
    private function readToArchiveList(BackupRun $run, string $rel): array
    {
        $abs  = $this->absolute($run, $rel);
        $list = [];

        if (is_file($abs) && ($fh = fopen($abs, 'rb')) !== false) {
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (isset($decoded['path'])) {
                    $list[] = $decoded['path'];
                }
            }
            fclose($fh);
        }

        return $list;
    }

    /* ---- Archive one batch of files ------------------------------------ */

    private function archiveBatch(BackupRun $run, array $state): StageStepResult
    {
        $budget     = max(1, (int) config('backup.files.batch_bytes', 64 * 1024 * 1024));
        $volumeCap  = max(1, (int) config('backup.volume_bytes', 512 * 1024 * 1024));
        $throttleMs = (int) config('backup.files.throttle_ms', 0);

        $list   = (array) json_decode((string) Storage::disk($this->stagingDisk())->get($state['filelist']), true);
        $cursor = (int) $state['cursor'];
        $vol    = (int) $state['vol'];

        $volRel = $this->volumePath($run, $vol);
        $volAbs = $this->absolute($run, $volRel);
        $handle = fopen($volAbs, 'ab');

        // Track the volume's compressed size ourselves: buffered writes aren't
        // reflected by filesize() until flush, so start from the flushed size on
        // disk (bytes from prior steps) and add what we write this step.
        $volBytes  = $this->size($volAbs);
        $processed = 0;
        $part      = null;

        while ($cursor < (int) $state['total'] && $processed < $budget) {
            $rel   = (string) $list[$cursor];
            $entry = $this->streamFileIntoVolume($handle, $state['root'].'/'.$rel, $rel, $vol);

            $this->appendEntry($run, $state['entries'], $entry);
            $state['counts']['archived'] = (int) $state['counts']['archived'] + 1;
            $state['vol_has_content']    = true;
            $volBytes += array_sum(array_map(fn ($s) => $s[1], $entry['segments']));
            $processed += max(1, (int) $entry['size']);
            $cursor++;

            // Checkpoint after EVERY file, not just at the end of the whole
            // batch: appendEntry() and the fwrite()s above are real,
            // immediate disk writes that a checkpoint saved only once per
            // step() call can't undo. Without this, a process killed
            // mid-batch resumes from the stale pre-batch cursor and
            // re-archives files already appended to the (still-open, 'ab'
            // mode) volume and entries.ndjson — duplicate segments and
            // duplicate manifest entries for the same path. Checkpointing
            // per file means a crash loses at most the in-flight file, never
            // duplicates a completed one.
            $state['cursor'] = $cursor;
            $state['vol']    = $vol;
            $this->checkpointNow($run, $state);

            if ($throttleMs > 0) {
                usleep($throttleMs * 1000);
            }

            // Volume filled → seal it as a part and end the step.
            if ($volBytes >= $volumeCap) {
                fclose($handle);
                $handle = null;
                $part = $this->finaliseVolume($run, $vol);
                $state['counts']['volumes'] = (int) $state['counts']['volumes'] + 1;
                $vol++;
                $state['vol_has_content'] = false;
                $state['vol']             = $vol;
                $this->checkpointNow($run, $state);
                break;
            }
        }

        if (is_resource($handle)) {
            fclose($handle);
        }

        $state['cursor'] = $cursor;
        $state['vol']    = $vol;

        $note = 'files: '.$state['counts']['archived'].'/'.$state['total'].' archived';

        // Reserve the top ~5% of this stage's range for the volume-flush/manifest
        // phases that follow the archiving loop.
        $fraction = (int) $state['total'] > 0 ? (min($cursor, (int) $state['total']) / (int) $state['total']) * 0.95 : 0.95;

        return StageStepResult::progress($state, $part, $note, $fraction);
    }

    /**
     * Stream one file into the open volume as gzipped blocks; return its manifest
     * entry (with the volume/segment map needed to extract it on restore).
     */
    private function streamFileIntoVolume($handle, string $abs, string $rel, int $vol): array
    {
        $ctx      = hash_init('sha256');
        $segments = [];
        $size     = 0;

        $in = @fopen($abs, 'rb');
        if ($in !== false) {
            while (! feof($in)) {
                $block = fread($in, self::BLOCK);
                if ($block === false || $block === '') {
                    break;
                }

                hash_update($ctx, $block);
                $size += strlen($block);

                $gz = (string) gzencode($block, 6);
                fseek($handle, 0, SEEK_END);
                $offset = ftell($handle);
                fwrite($handle, $gz);

                $segments[] = [$offset, strlen($gz), strlen($block)]; // [comp offset, comp len, raw len]
            }
            fclose($in);
        }

        return [
            'path'     => $rel,
            'size'     => $size,
            'mtime'    => (int) @filemtime($abs),
            'sha256'   => hash_final($ctx),
            'vol'      => $vol,
            'segments' => $segments,
        ];
    }

    private function finaliseVolume(BackupRun $run, int $vol): array
    {
        $rel = $this->volumePath($run, $vol);
        $abs = $this->absolute($run, $rel);

        return [
            'type'   => BackupChunk::TYPE_FILES,
            'name'   => 'vol-'.$vol,
            'disk'   => $this->stagingDisk(),
            'path'   => $rel,
            'bytes'  => $this->size($abs),
            'sha256' => is_file($abs) ? hash_file('sha256', $abs) : null,
            'meta'   => ['kind' => 'volume', 'index' => $vol],
        ];
    }

    /* ---- Manifest (hash baseline) -------------------------------------- */

    private function writeManifest(BackupRun $run, array $state): array
    {
        $files = [];
        $abs   = $this->absolute($run, $state['entries']);

        if (is_file($abs) && ($fh = fopen($abs, 'rb')) !== false) {
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line !== '') {
                    $files[] = json_decode($line, true);
                }
            }
            fclose($fh);
        }

        $manifest = [
            'schema'          => 1,
            'run_id'          => (int) $run->getKey(),
            'baseline_run_id' => $state['baseline_run_id'],
            'root'            => $state['root'],
            'counts'          => $state['counts'],
            'files'           => $files,
        ];

        $rel = 'backups/'.$run->getKey().'/files/files-manifest.json.gz';
        $gz  = (string) gzencode((string) json_encode($manifest), 6);
        Storage::disk($this->stagingDisk())->put($rel, $gz);

        return [
            'type'   => BackupChunk::TYPE_FILES,
            'name'   => 'files-manifest',
            'disk'   => $this->stagingDisk(),
            'path'   => $rel,
            'bytes'  => strlen($gz),
            'sha256' => hash('sha256', $gz),
            'rows'   => (int) $state['counts']['file_count'],
            'meta'   => array_merge(['kind' => 'manifest'], $state['counts']),
        ];
    }

    /** Load the previous successful full backup's file manifest, indexed by path. */
    private function loadBaseline(BackupRun $run): array
    {
        $prev = BackupRun::query()
            ->successful()
            ->where('profile', $run->profile)
            ->where('id', '<', $run->getKey())
            ->orderByDesc('id')
            ->first();

        if (! $prev) {
            return ['run_id' => null, 'map' => []];
        }

        $part = $prev->parts()
            ->where('type', BackupChunk::TYPE_FILES)
            ->where('name', 'files-manifest')
            ->first();

        if (! $part || ! Storage::disk($part->disk)->exists($part->path)) {
            return ['run_id' => null, 'map' => []];
        }

        // The baseline manifest is encrypted at rest (Chunk 2.4) — decrypt before gunzip.
        $bytes = (string) Storage::disk($part->disk)->get($part->path);
        if (($part->meta['encrypted'] ?? false) === true) {
            $bytes = app(\App\Services\Backup\BackupCipher::class)->decryptData($bytes);
        }

        $data = json_decode((string) gzdecode($bytes), true);
        $map  = [];

        foreach ((array) ($data['files'] ?? []) as $entry) {
            if (empty($entry['deleted']) && ! empty($entry['path'])) {
                $map[$entry['path']] = $entry;
            }
        }

        return ['run_id' => (int) $prev->getKey(), 'map' => $map];
    }

    /**
     * Persist stage_state immediately, mid-step, keeping stage_index as-is —
     * see the comment at the per-file checkpoint call site in archiveBatch().
     */
    private function checkpointNow(BackupRun $run, array $state): void
    {
        $run->setCheckpoint([
            'stage_index' => $run->checkpoint()['stage_index'],
            'stage_state' => $state,
        ]);
        $run->save();
    }

    /* ---- Filesystem helpers -------------------------------------------- */

    private function isExcluded(string $rel, array $excludes): bool
    {
        foreach ($excludes as $ex) {
            $ex = str_replace('\\', '/', trim($ex, "/\\"));
            if ($ex !== '' && ($rel === $ex || Str::startsWith($rel, $ex.'/'))) {
                return true;
            }
        }

        return false;
    }

    private function volumePath(BackupRun $run, int $vol): string
    {
        return 'backups/'.$run->getKey().'/files/vol-'.$vol.'.oevol';
    }

    /** The LOCAL staging disk parts are written to before encryption/transport (2.4). */
    private function stagingDisk(): string
    {
        return (string) config('backup.staging_disk', 'local');
    }

    /** Absolute path on the local staging disk, ensuring the dir exists. */
    private function absolute(BackupRun $run, string $rel): string
    {
        $abs = Storage::disk($this->stagingDisk())->path($rel);
        $dir = dirname($abs);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $abs;
    }

    private function size(string $abs): int
    {
        clearstatcache(true, $abs);

        return is_file($abs) ? (int) filesize($abs) : 0;
    }

    private function resetFile(BackupRun $run, string $rel): void
    {
        $abs = $this->absolute($run, $rel);
        @file_put_contents($abs, '');
    }

    private function appendEntry(BackupRun $run, string $rel, array $entry): void
    {
        $abs = $this->absolute($run, $rel);
        file_put_contents($abs, json_encode($entry).PHP_EOL, FILE_APPEND);
    }

    private function cleanupWorkingFiles(BackupRun $run, array $state): void
    {
        $disk = $this->stagingDisk();
        foreach ([$state['entries'], $state['filelist']] as $rel) {
            if (Storage::disk($disk)->exists($rel)) {
                Storage::disk($disk)->delete($rel);
            }
        }
        Storage::disk($disk)->deleteDirectory('backups/'.$run->getKey().'/files/_work');
    }
}
