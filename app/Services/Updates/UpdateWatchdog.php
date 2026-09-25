<?php

namespace App\Services\Updates;

use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use Illuminate\Support\Facades\Log;

/**
 * UpdateWatchdog — reclaims UpdateHistory rows abandoned mid-apply (the
 * initiating admin closed the tab mid-poll, so nothing ever calls advance()
 * again — OR the whole PHP process was killed outright, which is the same
 * "nothing will ever call advance() again" situation from this class's own
 * point of view) and alerts admins. Mirrors BackupJanitor::cleanupPartials()
 * for the backup engine, which this update-side gap was missing entirely:
 * the shared BackupLock already auto-releases once stale (BackupJanitor does
 * that for ANY stale lock, update or backup), but the UpdateHistory row
 * itself sat in its last non-terminal status forever with no cron-driven
 * resolution and no admin notification that it happened.
 *
 * Routes every reclaimed row through UpdateApplier::fail() rather than
 * marking it failed directly — a process-killed update can have left the
 * working tree/vendor mid-swap exactly the same way a normal in-process
 * failure can, and fail() already knows how to tell the difference (attempt
 * a real rollback once $history->step is past the destructive-phase
 * boundary, only then turn maintenance mode back off and disarm the
 * Recovery Console). A prior version of this method always cleared
 * maintenance mode unconditionally on reclaim — safe for the common case
 * (an abandoned tab, nothing destructive ever started), but wrong for a
 * genuine mid-swap crash: it would have silently reopened a half-updated
 * site to real traffic instead of leaving it in maintenance for an operator
 * to check, which is the one guarantee this whole failure path exists to
 * provide.
 */
class UpdateWatchdog
{
    public function __construct(private readonly BackupLock $lock) {}

    /** @return int number of rows reclaimed */
    public function reclaimStale(): int
    {
        // An update that got past the swap is FINISHABLE — the new code is on disk and
        // only finalize/verify remain — so try to complete it before deciding it was
        // abandoned and rolling it back (the request-driven resume normally beats this
        // by hours; this covers a site nobody is visiting).
        app(InterruptedUpdateResumer::class)->resume();

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
            Log::channel(config('updates.log_channel', 'stack'))->warning('Watchdog reclaiming a stale update.', [
                'history' => $history->getKey(), 'to' => $history->to_version, 'last_step' => $history->step,
            ]);

            app(UpdateApplier::class)->fail(
                $history,
                $history->step,
                'Reclaimed — abandoned mid-update (a tab was likely closed, or the process crashed); '.
                'rolled back if this had gone past the point of no return, verify site state manually either way.'
            );

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
