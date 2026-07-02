<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateChecker;
use Illuminate\Console\Command;

/**
 * Update & Recovery System (Module 21, Chunk 1.2) — scheduled/manual check tier.
 *
 * Forces a fresh check and refreshes the cached result so the admin badge/banner
 * (Chunk 1.4) is up to date without waiting for a lazy page-load check. Runs daily
 * from the scheduler (routes/console.php) and can be triggered manually via CLI.
 */
class CheckForUpdates extends Command
{
    protected $signature = 'oeparts:update:check {--json : Output the status as JSON}';

    protected $description = 'Check for available OeParts updates and refresh the cached result';

    public function handle(UpdateChecker $checker): int
    {
        $status = $checker->check(force: true);

        if ($this->option('json')) {
            $this->line((string) json_encode($status->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if (! $status->reachable) {
            // Transient network issues must not make the scheduled job "fail".
            $this->warn('Update server unreachable: '.$status->error);
            $this->line('Current version: '.$status->currentVersion);

            return self::SUCCESS;
        }

        $this->info('Current version: '.$status->currentVersion);
        $this->info('Latest version:  '.($status->latestVersion ?? 'unknown'));

        if (! $status->updateAvailable) {
            $this->info('You are up to date.');

            return self::SUCCESS;
        }

        $label = $status->security ? 'SECURITY UPDATE' : 'Update';
        $this->newLine();
        $this->warn($label.' available: '.$status->currentVersion.' → '.$status->latestVersion);

        if ($status->isMultiStep()) {
            $this->line('  Multi-step path: '.implode(' → ', $status->upgradePath));
        }

        return self::SUCCESS;
    }
}
