<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupJanitor;
use Illuminate\Console\Command;

/**
 * BackupJanitor::cleanupPartials() reclaims backup runs abandoned mid-way
 * (e.g. an admin closes the tab or navigates away while a "Run backup now"
 * poll is still in progress) and releases the shared backup/update lock they
 * hold — but nothing ever called it: it was reachable only via BackupRetentionService
 * (which explicitly does NOT call it — "Failed/partial runs are the
 * BackupJanitor's job, not retention's") and BackupDashboard's unrelated
 * purgeFiles() (the delete action). A run abandoned this way sat `running`
 * forever, silently blocking every future backup AND update (PreflightService's
 * shared-lock check) until an operator noticed and manually intervened.
 */
class CleanupStaleBackups extends Command
{
    protected $signature = 'oeparts:backup:cleanup-stale';

    protected $description = 'Reclaim backup runs abandoned mid-progress and release the stale shared lock (Backup/Update Engine).';

    public function handle(BackupJanitor $janitor): int
    {
        $cleaned = $janitor->cleanupPartials();

        $this->info($cleaned === 1 ? '1 stale backup run reclaimed.' : "{$cleaned} stale backup runs reclaimed.");

        return self::SUCCESS;
    }
}
