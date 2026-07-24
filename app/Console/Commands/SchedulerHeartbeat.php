<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'scheduler:heartbeat';

    protected $description = 'Update scheduler heartbeat for health monitoring';

    public function handle(): int
    {
        $this->info('Updating scheduler heartbeat...');

        // Store heartbeat timestamp in cache with 2-minute TTL
        // Health check will fail if this is older than 2 minutes
        Cache::put('scheduler_heartbeat', now()->toIso8601String(), 120);

        $this->info('Scheduler heartbeat updated successfully.');

        return Command::SUCCESS;
    }
}
