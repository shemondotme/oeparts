<?php

namespace App\Console\Commands;

use App\Services\Cleanup\CleanupScheduler;
use Illuminate\Console\Command;

/** oeparts:cleanup:auto — the scheduler tick (every 15 minutes). See CleanupScheduler for the rules. */
class RunAutoCleanup extends Command
{
    protected $signature = 'oeparts:cleanup:auto {--budget= : Seconds of work per tick (default config cleanup.cli_tick_seconds)}';

    protected $description = 'Continue an undriven cleanup run, or start the automatic cleanup batch when it is due';

    public function handle(CleanupScheduler $scheduler): int
    {
        $result = $scheduler->tick($this->option('budget') !== null ? (float) $this->option('budget') : null);

        $this->line("Automatic cleanup: {$result}");

        return self::SUCCESS;
    }
}
