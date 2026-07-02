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
        $staleAfter = (int) config('backup.stale_after_seconds', 3600);
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
            $this->purgeFiles($run);

            $meta = $run->meta ?? [];
            $meta['cleaned_at'] = now()->toIso8601String();
            $run->meta = $meta;

            // A crashed run that we're reaping is, definitively, no longer running.
            if ($run->status === BackupRun::STATUS_RUNNING) {
                $run->status      = BackupRun::STATUS_FAILED;
                $run->error       = $run->error ?: 'Abandoned mid-run; reclaimed by janitor.';
                $run->finished_at = $run->finished_at ?: now();
            }

            $run->save();
            $cleaned++;
        }

        $this->releaseStaleLock($staleAfter);

        return $cleaned;
    }

    /** Delete every stored part + manifest + the run directory for a run.
     *  Shared with BackupRetentionService (GFS pruning). */
    public function purgeFiles(BackupRun $run): void
    {
        try {
            foreach ($run->parts as $part) {
                if ($part->path && Storage::disk($part->disk)->exists($part->path)) {
                    Storage::disk($part->disk)->delete($part->path);
                }
            }

            if ($run->manifest_path && Storage::disk($run->disk)->exists($run->manifest_path)) {
                Storage::disk($run->disk)->delete($run->manifest_path);
            }

            // Remove the run's directory wholesale (mops up any orphaned bytes).
            $dir = 'backups/'.$run->getKey();
            if (Storage::disk($run->disk)->exists($dir)) {
                Storage::disk($run->disk)->deleteDirectory($dir);
            }
        } catch (\Throwable $e) {
            Log::channel(config('updates.log_channel', 'stack'))
                ->warning('Janitor could not purge backup '.$run->getKey().': '.$e->getMessage());
        }
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
