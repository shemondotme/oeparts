<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateWatchdog;
use Illuminate\Console\Command;

/**
 * UpdateWatchdog::reclaimStale() reclaims UpdateHistory rows abandoned
 * mid-apply (e.g. an admin closes the tab while the poll-driven apply FSM is
 * still in progress) and releases the shared backup/update lock they hold —
 * mirrors oeparts:backup:cleanup-stale / BackupJanitor, which covered the
 * same scenario for backup runs but had no update-side equivalent: a stuck
 * UpdateHistory row previously sat non-terminal forever with no cron-driven
 * resolution and no admin alert that it happened.
 */
class CleanupStaleUpdates extends Command
{
    protected $signature = 'oeparts:update:cleanup-stale';

    protected $description = 'Reclaim updates abandoned mid-apply and release the stale shared lock (Update Engine).';

    public function handle(UpdateWatchdog $watchdog): int
    {
        $reclaimed = $watchdog->reclaimStale();

        $this->info($reclaimed === 1 ? '1 stale update reclaimed.' : "{$reclaimed} stale updates reclaimed.");

        return self::SUCCESS;
    }
}
