<?php

namespace App\Console\Commands;

use App\Services\CoreWebVitalsService;
use Illuminate\Console\Command;

class CwvSnapshot extends Command
{
    protected $signature = 'cwv:snapshot';

    protected $description = 'Record a Core Web Vitals snapshot for trend tracking on the SEO Health Dashboard — no-ops when CrUX is unconfigured or has no data yet';

    public function handle(CoreWebVitalsService $service): int
    {
        $result = $service->snapshot();

        if (isset($result['error'])) {
            $this->warn("Core Web Vitals snapshot skipped: {$result['error']}");
        } elseif (isset($result['insufficientData'])) {
            $this->info('Core Web Vitals snapshot skipped: CrUX has no data for this origin yet.');
        } else {
            $this->info('Core Web Vitals snapshot recorded.');
        }

        return Command::SUCCESS;
    }
}
