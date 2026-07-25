<?php

namespace App\Console\Commands;

use App\Services\CacheMetricsService;
use Illuminate\Console\Command;

class CacheSnapshot extends Command
{
    protected $signature = 'cache:snapshot';

    protected $description = 'Record a cache-metrics snapshot for history/trend tracking and alerting — runs even when nobody is viewing the admin panel';

    public function handle(CacheMetricsService $service): int
    {
        $result = $service->snapshot();

        $this->info("Cache snapshot recorded: hit rate is {$result['hit_rate']}%.");

        return Command::SUCCESS;
    }
}
