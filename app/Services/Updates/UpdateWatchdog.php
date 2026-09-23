<?php

namespace App\Services\Updates;

use App\Jobs\NotifyAdminsOfUpdateResult;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use Illuminate\Support\Facades\Log;

/**
 * UpdateWatchdog — reclaims UpdateHistory rows abandoned mid-apply (e.g. the
 * initiating admin closed the tab mid-poll, so nothing ever calls advance()
 * again) and alerts admins. Mirrors BackupJanitor::cleanupPartials() for the
 * backup engine, which this update-side gap was missing entirely: the shared
 * BackupLock already auto-releases once stale (BackupJanitor does that for
 * ANY stale lock, update or backup), but the UpdateHistory row itself sat in
 * its last non-terminal status forever with no cron-driven resolution and no
 * admin notification that it happened.
 */
class UpdateWatchdog
{
    public function __construct(private readonly BackupLock $lock) {}

    /** @return int number of rows reclaimed */
    public function reclaimStale(): int
    {
        $staleAfter = (int) config('updates.stale_after_seconds', 7200);
        $reclaimed = 0;

        $stale = UpdateHistory::query()
            ->whereNotIn('status', [
                UpdateHistory::STATUS_SUCCESS,
                UpdateHistory::STATUS_FAILED,
                UpdateHistory::STATUS_ROLLED_BACK,
            ])
            // updated_at, not started_at — every advance() step call saves()
            // the row on success, so a genuinely still-advancing multi-poll
            // update is never falsely reclaimed; only a row whose last
            // checkpoint write was long ago (poll loop dead) qualifies.
            ->where('updated_at', '<', now()->subSeconds($staleAfter))
            ->get();

        foreach ($stale as $history) {
            $history->status = UpdateHistory::STATUS_FAILED;
            $history->error = 'Reclaimed — abandoned mid-update (a tab was likely closed); verify site state manually.';
            $history->finished_at = now();
            $history->save();

            Log::channel(config('updates.log_channel', 'stack'))->warning('Watchdog reclaimed a stale update.', [
                'history' => $history->getKey(), 'to' => $history->to_version, 'last_step' => $history->step,
            ]);

            dispatch(new NotifyAdminsOfUpdateResult([
                'from_version' => $history->from_version,
                'to_version' => $history->to_version,
                'success' => false,
                'rolled_back' => false,
                'error' => $history->error,
                'started_at' => optional($history->started_at)->toIso8601String(),
                'trigger' => $history->initiated_by ? 'manual' : 'auto',
            ]));

            $reclaimed++;
        }

        $this->releaseStaleLock($staleAfter);

        return $reclaimed;
    }

    /** Same primitive BackupJanitor::releaseStaleLock() uses — the lock is shared. */
    private function releaseStaleLock(int $staleAfter): void
    {
        if ($this->lock->isLocked() && $this->lock->isStale($staleAfter)) {
            Log::channel(config('updates.log_channel', 'stack'))
                ->warning('Watchdog released a stale backup/update lock.', $this->lock->owner());

            $this->lock->release();
        }
    }
}
