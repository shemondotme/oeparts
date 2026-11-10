<?php

namespace App\Console\Commands;

use App\Services\GoogleSearchConsoleService;
use Illuminate\Console\Command;

class GscSnapshot extends Command
{
    protected $signature = 'gsc:snapshot {--days=7 : How many recent days to (re)fetch; use a larger number once to backfill (max 90)}';

    protected $description = 'Store daily Google Search Console totals (clicks, impressions, CTR, position) for the SEO Health Dashboard trend — no-ops when Search Console is not configured';

    public function handle(GoogleSearchConsoleService $service): int
    {
        if (! $service->isConfigured()) {
            $this->info('Search Console is not configured — nothing to record.');

            return self::SUCCESS;
        }

        $days = max(1, min(90, (int) $this->option('days')));
        $result = $service->snapshotDaily($days);

        if (isset($result['error'])) {
            $this->warn("Search Console snapshot skipped: {$result['error']}");

            return self::SUCCESS; // a transient API problem must not fail the scheduler
        }

        $this->info("Stored {$result['stored']} day(s) of Search Console totals.");

        return self::SUCCESS;
    }
}
