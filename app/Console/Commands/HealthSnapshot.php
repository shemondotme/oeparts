<?php

namespace App\Console\Commands;

use App\Services\HealthCheckService;
use Illuminate\Console\Command;

class HealthSnapshot extends Command
{
    protected $signature = 'health:snapshot';

    protected $description = 'Record a health-check snapshot for history/uptime tracking and alerting — runs even when nobody is viewing the admin panel';

    public function handle(HealthCheckService $service): int
    {
        $result = $service->snapshot();

        $this->info("Health snapshot recorded: overall status is {$result['status']}.");

        return Command::SUCCESS;
    }
}
