<?php

namespace App\Services\Backup;

use App\Models\BackupPart;
use App\Models\BackupRun;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\Exceptions\BackupException;
use App\Services\Updates\UpdateChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * BackupManager (Module 14/21, Chunk 2.1) — the Backup Engine's chunked,
 * resumable finite state machine.
 *
 * Lifecycle:
 *   start()   → acquires the shared lock, creates a `running` BackupRun,
 *               initialises the checkpoint.
 *   advance() → performs ONE chunk of work (one stage step) and persists the
 *               checkpoint. Designed to be called once per AJAX poll; also
 *               idempotent enough to resume after a crash.
 *   finalize()→ (reached when all stages are done) computes totals, writes the
 *               manifest + checksum, marks success, releases the lock.
 *   fail()    → marks failed, releases the lock; the partial files are left for
 *               the BackupJanitor to clean.
 *
 * Pure PHP, no queue worker required (rule #41). The concrete work lives in
 * pluggable stages (Chunks 2.2–2.4); this class only orchestrates them.
 */
class BackupManager
{
    /** Safety bound for the synchronous run() loop (defensive, never hit in practice). */
    private const MAX_STEPS = 1_000_000;

    public function __construct(
        private readonly StageRegistry $stages,
        private readonly BackupLock $lock,
        private readonly BackupManifest $manifest,
    ) {}

    /**
     * Begin a backup run and acquire the shared update/backup lock.
     *
     * @throws BackupException                                          on an invalid profile
     * @throws \App\Services\Backup\Exceptions\BackupLockException      if a backup/update is already running
     */
    public function start(
        string $profile = BackupRun::PROFILE_FULL,
        string $trigger = BackupRun::TRIGGER_MANUAL,
        array $meta = [],
        bool $acquireLock = true,
    ): BackupRun {
        if (! in_array($profile, [
            BackupRun::PROFILE_UPDATE_SAFETY,
            BackupRun::PROFILE_FULL,
            BackupRun::PROFILE_DATABASE_ONLY,
            BackupRun::PROFILE_FILES_ONLY,
        ], true)) {
            throw new BackupException('Unknown backup profile: '.$profile);
        }

        $run = BackupRun::create([
            'profile'     => $profile,
            'status'      => BackupRun::STATUS_RUNNING,
            'trigger'     => $trigger,
            'disk'        => (string) config('backup.disk', 'local'),
            'encrypted'   => (bool) config('backup.encryption.enabled', true),
            'app_version' => app(UpdateChecker::class)->currentVersion(),
            'php_version' => PHP_VERSION,
            'db_version'  => $this->databaseVersion(),
            'started_at'  => now(),
            // lock_owned=false lets the Update Engine own the shared lock across the
            // WHOLE apply while its pre-update backup step runs (rule #48).
            'meta'        => array_merge($meta, ['lock_owned' => $acquireLock]),
        ]);

        // Acquire the lock AFTER the row exists so the owner token carries the id.
        // If someone else holds it, drop the just-created row and surface the error.
        if ($acquireLock) {
            try {
                $this->lock->acquire($run->lockOwner());
            } catch (\Throwable $e) {
                $run->delete();
                throw $e;
            }
        }

        $run->setCheckpoint(['stage_index' => 0, 'stage_state' => []]);
        $run->save();

        return $run;
    }

    /**
     * Advance the run by one chunk. Returns a progress snapshot for the poll UI.
     */
    public function advance(BackupRun $run): BackupProgress
    {
        if ($run->status === BackupRun::STATUS_SUCCESS) {
            return BackupProgress::success($run);
        }

        if ($run->status === BackupRun::STATUS_FAILED) {
            return BackupProgress::failed($run, (string) $run->error);
        }

        $stages     = $this->stages->forProfile($run->profile);
        $checkpoint = $run->checkpoint();
        $index      = $checkpoint['stage_index'];

        // All stages consumed → finalise.
        if ($index >= count($stages)) {
            try {
                return $this->finalize($run);
            } catch (\Throwable $e) {
                Log::channel(config('updates.log_channel', 'stack'))
                    ->error('Backup run '.$run->getKey().' failed during finalize: '.$e->getMessage(), ['exception' => $e]);

                return $this->fail($run, '[finalize] '.$e->getMessage());
            }
        }

        /** @var BackupStage $stage */
        $stage = $stages[$index];

        try {
            $result = $stage->step($run, $checkpoint['stage_state']);
        } catch (\Throwable $e) {
            return $this->fail($run, '['.$stage->key().'] '.$e->getMessage());
        }

        // ->parts holds any earlier units from this (possibly batched) step, in
        // chronological order; ->part holds the last one. Register in that
        // order so auto-assigned `sequence` numbers stay chronological.
        foreach ($result->parts as $partAttrs) {
            $this->registerPart($run, $stage->key(), $partAttrs);
        }
        if ($result->part !== null) {
            $this->registerPart($run, $stage->key(), $result->part);
        }

        $fraction = $result->fraction;

        if ($result->done) {
            $checkpoint['stage_index'] = $index + 1;
            $checkpoint['stage_state'] = [];
            $fraction = 1.0;
        } else {
            $checkpoint['stage_state'] = $result->state;
        }

        $run->setCheckpoint($checkpoint);
        $run->save();

        $totalStages = max(1, count($stages));
        $percent     = (int) round((($index + $fraction) / $totalStages) * 100);

        return BackupProgress::running($run, $stage->key(), $result->message, $percent);
    }

    /**
     * Drive a run to a terminal state synchronously (CLI / scheduler / sync
     * queue / tests). Prefer advance() behind an AJAX poll for the admin UI.
     */
    public function run(BackupRun $run): BackupRun
    {
        $steps = 0;

        while (! $run->isTerminal() && $steps++ < self::MAX_STEPS) {
            $this->advance($run);
            $run->refresh();
        }

        return $run;
    }

    /** Mark the run failed and release the lock (partial files left for the janitor). */
    public function fail(BackupRun $run, string $error): BackupProgress
    {
        $run->status      = BackupRun::STATUS_FAILED;
        $run->error       = Str::limit($error, 2000, '');
        $run->finished_at = now();
        $run->save();

        $this->releaseLockIfOwned($run);

        Log::channel(config('updates.log_channel', 'stack'))
            ->error('Backup run '.$run->getKey().' failed: '.$error);

        return BackupProgress::failed($run, $error);
    }

    /**
     * Finalise a run whose stages have all completed: total up the parts, write
     * the manifest + checksum, mark success, release the lock.
     */
    private function finalize(BackupRun $run): BackupProgress
    {
        $run->loadMissing('parts');

        $run->total_bytes = (int) $run->parts->sum('bytes');
        $run->part_count  = $run->parts->count();
        $run->save(); // persist totals BEFORE the manifest reads them

        $run->manifest_path = $this->manifest->write($run);
        $run->checksum      = $this->manifest->checksum($run);
        $run->status        = BackupRun::STATUS_SUCCESS;
        $run->finished_at   = now();
        $run->clearCheckpoint();
        $run->save();

        $this->releaseLockIfOwned($run);

        return BackupProgress::success($run);
    }

    /** Release the shared lock only if this run acquired it (not when the updater owns it). */
    private function releaseLockIfOwned(BackupRun $run): void
    {
        if (($run->meta['lock_owned'] ?? true) === true) {
            $this->lock->release();
        }
    }

    /** Persist a backup_parts row emitted by a stage step. */
    private function registerPart(BackupRun $run, string $stageKey, array $attrs): BackupPart
    {
        $type = (string) ($attrs['type'] ?? $stageKey);

        $sequence = $attrs['sequence']
            ?? $run->parts()->where('type', $type)->count();

        return $run->parts()->create([
            'type'     => $type,
            'sequence' => (int) $sequence,
            'name'     => $attrs['name'] ?? null,
            'disk'     => $attrs['disk'] ?? $run->disk,
            'path'     => (string) ($attrs['path'] ?? ''),
            'sha256'   => $attrs['sha256'] ?? null,
            'bytes'    => (int) ($attrs['bytes'] ?? 0),
            'rows'     => $attrs['rows'] ?? null,
            'meta'     => $attrs['meta'] ?? null,
        ]);
    }

    private function databaseVersion(): ?string
    {
        try {
            $row = DB::selectOne('select version() as v');

            return $row->v ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
