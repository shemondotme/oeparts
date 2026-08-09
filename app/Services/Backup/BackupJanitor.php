<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * BackupJanitor (Module 14/21, Chunk 2.1) — reclaims the debris left by backup
 * runs that never reached success: failed runs and runs that crashed mid-way
 * (still `running` but older than the stale threshold).
 *
 * It deletes the on-disk parts + manifest for those runs (freeing space), stamps
 * the run as cleaned, and releases a stale shared lock so a new backup/update
 * can proceed. It does NOT delete backup_runs history rows, and never touches
 * successful backups — GFS retention pruning of those is Chunk 2.6.
 */
class BackupJanitor
{
    public function __construct(private readonly BackupLock $lock) {}

    /**
     * Clean partial/failed runs and release a stale lock.
     *
     * @return int number of runs cleaned
     */
    public function cleanupPartials(): int
    {
        $staleAfter = (int) settings('backup.stale_after_seconds', config('backup.stale_after_seconds', 3600));
        $cleaned    = 0;

        $runs = BackupRun::query()
            ->partials()
            ->whereNull('meta->cleaned_at')
            ->where(function ($q) use ($staleAfter) {
                $q->where('status', BackupRun::STATUS_FAILED)
                    ->orWhere(function ($q) use ($staleAfter) {
                        $q->where('status', BackupRun::STATUS_RUNNING)
                            ->where('started_at', '<', now()->subSeconds($staleAfter));
                    });
            })
            ->get();

        foreach ($runs as $run) {
            $purged = $this->purgeFiles($run);

            // A crashed run that we're reaping is, definitively, no longer
            // running — mark it FAILED regardless of whether the file purge
            // itself succeeded, so it stops looking "running" and releases
            // its hold on the shared lock either way.
            if ($run->status === BackupRun::STATUS_RUNNING) {
                $run->status      = BackupRun::STATUS_FAILED;
                $run->error       = $run->error ?: 'Abandoned mid-run; reclaimed by janitor.';
                $run->finished_at = $run->finished_at ?: now();
            }

            if ($purged) {
                $meta = $run->meta ?? [];
                $meta['cleaned_at'] = now()->toIso8601String();
                $run->meta = $meta;
                $cleaned++;
            }
            // else: leave meta.cleaned_at unset. The whereNull('meta->cleaned_at')
            // filter above means this run gets picked up again next run, so a
            // failed deletion (unreachable disk, permission error, ...) is
            // retried instead of silently leaking storage forever.

            $run->save();
        }

        $this->releaseStaleLock($staleAfter);

        return $cleaned;
    }

    /**
     * Delete every stored part + manifest + the run directory for a run.
     * Shared with BackupRetentionService (GFS pruning).
     *
     * @return bool true if every deletion succeeded; false if anything
     *              failed (already logged) — callers must NOT mark the run
     *              as cleaned/pruned when this returns false, or the failed
     *              deletion is never retried and the storage leaks forever.
     */
    public function purgeFiles(BackupRun $run): bool
    {
        $ok = true;

        try {
            foreach ($run->parts as $part) {
                if ($part->path && Storage::disk($part->disk)->exists($part->path)) {
                    $ok = Storage::disk($part->disk)->delete($part->path) && $ok;
                }
            }

            if ($run->manifest_path && Storage::disk($run->disk)->exists($run->manifest_path)) {
                $ok = Storage::disk($run->disk)->delete($run->manifest_path) && $ok;
            }

            // Remove the run's directory wholesale (mops up any orphaned bytes).
            $dir = 'backups/'.$run->getKey();
            if (Storage::disk($run->disk)->exists($dir)) {
                $ok = Storage::disk($run->disk)->deleteDirectory($dir) && $ok;
            }
        } catch (\Throwable $e) {
            Log::channel(config('updates.log_channel', 'stack'))
                ->warning('Janitor could not purge backup '.$run->getKey().': '.$e->getMessage());

            $ok = false;
        }

        return $ok;
    }

    /** Release the shared lock if it's older than the stale threshold. */
    private function releaseStaleLock(int $staleAfter): void
    {
        if ($this->lock->isLocked() && $this->lock->isStale($staleAfter)) {
            Log::channel(config('updates.log_channel', 'stack'))
                ->warning('Janitor released a stale backup/update lock.', $this->lock->owner());

            $this->lock->release();
        }
    }
}
