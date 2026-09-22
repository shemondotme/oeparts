<?php

namespace App\Jobs;

use App\Models\BackupRun;
use App\Services\Updates\ProductionRestoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs a full (files + database) production restore off the request cycle —
 * mirrors RestoreBackupJob's queued-dispatch pattern for the existing
 * files-only restore, but drives ProductionRestoreService instead (the
 * destructive, live-swap version). Same generous timeout as
 * RestoreBackupJob: a full restore reassembles + decrypts + swaps a
 * potentially large backup, well past a typical web request's limit.
 */
class RunProductionRestoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    // Explicit, not inherited from the queue worker's --tries=3: a genuine
    // $timeout breach SIGKILLs the worker process outright, well before
    // ProductionRestoreService::restore()'s own try/catch can run its
    // cleanup — BackupLock stays held, so an auto-retry wouldn't actually
    // re-run the destructive swap/DB-restore (acquire() fails fast on an
    // already-held lock), but it WOULD misreport the real cause ("a backup
    // or update is already in progress") instead of the real timeout, up
    // to 3 times. One attempt, fail loud, let UpdateWatchdog reclaim the
    // stale lock/history — a clear single failure beats 3 near-identical,
    // misleading ones for an operation this destructive.
    public int $tries = 1;

    public function __construct(
        public int $runId,
        public ?int $requestedBy = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(ProductionRestoreService $service): void
    {
        $run = BackupRun::find($this->runId);
        if (! $run) {
            // Narrow but real: the run existed at dispatch time (the admin
            // clicked Restore on it) but is gone by the time this executes
            // — e.g. a hard delete from elsewhere. Silently returning here
            // would leave the admin who clicked Restore never hearing
            // anything at all, unlike every other failure path below.
            Log::channel(config('updates.log_channel', 'stack'))
                ->error("Production restore could not start: backup run {$this->runId} no longer exists.");

            dispatch(new NotifyAdminsOfUpdateResult([
                'from_version' => null,
                'to_version' => 'restore-'.$this->runId,
                'success' => false,
                'rolled_back' => false,
                'error' => 'The selected backup no longer exists.',
                'started_at' => now()->toIso8601String(),
                'trigger' => 'restore',
            ]));

            return;
        }

        try {
            $history = $service->restore($run, $this->requestedBy);
        } catch (\Throwable $e) {
            // ProductionRestoreService::restore() can throw before an
            // UpdateHistory row exists at all (not restorable, or the shared
            // lock is held) — same "nothing to notifyResult() about yet" gap
            // AutoApplySecurityUpdate's own start()-failure catch covers for
            // updates; mirrored here so a restore that can't even start
            // isn't silently swallowed as just a failed-jobs row.
            Log::channel(config('updates.log_channel', 'stack'))
                ->error('Production restore could not start: '.$e->getMessage(), ['run' => $this->runId]);

            dispatch(new NotifyAdminsOfUpdateResult([
                'from_version' => null,
                'to_version' => 'restore-'.$this->runId,
                'success' => false,
                'rolled_back' => false,
                'error' => $e->getMessage(),
                'started_at' => now()->toIso8601String(),
                'trigger' => 'restore',
            ]));

            throw $e;
        }

        Log::channel(config('updates.log_channel', 'stack'))->notice('Production restore job finished.', [
            'run' => $this->runId,
            'requested_by' => $this->requestedBy,
            'history' => $history->getKey(),
            'status' => $history->status,
        ]);
    }
}
