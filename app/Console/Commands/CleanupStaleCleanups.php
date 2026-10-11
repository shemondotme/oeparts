<?php

namespace App\Console\Commands;

use App\Services\Cleanup\CleanupJanitor;
use Illuminate\Console\Command;

/**
 * oeparts:cleanup:cleanup-stale — hourly reclaim of cleanup runs whose driver disappeared.
 * Sibling of oeparts:backup:cleanup-stale and oeparts:update:cleanup-stale.
 */
class CleanupStaleCleanups extends Command
{
    protected $signature = 'oeparts:cleanup:cleanup-stale';

    protected $description = 'Re-drive or close cleanup runs that stopped making progress';

    public function handle(CleanupJanitor $janitor): int
    {
        $count = $janitor->reclaimStale();

        $this->info("Reclaimed {$count} stale cleanup run(s).");

        return self::SUCCESS;
    }
}
