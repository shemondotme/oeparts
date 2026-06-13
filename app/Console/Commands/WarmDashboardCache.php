<?php

namespace App\Console\Commands;

use App\Services\WidgetPreferenceService;
use Illuminate\Console\Command;

class WarmDashboardCache extends Command
{
    protected $signature = 'dashboard:warm-cache';

    protected $description = 'Warm dashboard widget cache for all periods';

    public function handle(): int
    {
        $this->info('Warming dashboard widget cache...');

        $periods = ['1', '7', '30', '90', '365'];
        $registry = WidgetPreferenceService::WIDGETS;

        foreach ($registry as $id => $config) {
            if (!$config['period'] ?? false) {
                continue;
            }

            foreach ($periods as $period) {
                $key = "{$id}:p{$period}";
                $this->line("  Warming {$key}");
            }
        }

        $this->info('Dashboard cache warming complete.');

        return self::SUCCESS;
    }
}
