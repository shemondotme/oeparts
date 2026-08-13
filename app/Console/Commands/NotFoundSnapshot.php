<?php

namespace App\Console\Commands;

use App\Models\NotFoundLog;
use Illuminate\Console\Command;

class NotFoundSnapshot extends Command
{
    protected $signature = 'notfound:snapshot';

    protected $description = 'Record an unresolved-404-count snapshot for trend tracking on the SEO Health Dashboard';

    public function handle(): int
    {
        $count = NotFoundLog::snapshot();

        $this->info("404 snapshot recorded: {$count} unresolved.");

        return Command::SUCCESS;
    }
}
