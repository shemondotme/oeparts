<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

/**
 * Update & Recovery System (Module 21, Chunk 2.6) — a queueable wrapper around
 * `oeparts:backup`, for running a backup off the request cycle from custom
 * automation (e.g. a scheduled job or console script) that wants an async
 * dispatch instead of shelling out to the command directly.
 *
 * NOT currently wired to the admin "Run backup now" action — that calls
 * BackupManager::start() directly and advances it via a short-poll loop
 * (BackupDashboard::pollBackup()) instead, specifically BECAUSE dispatching
 * this job there would run the WHOLE backup inline under QUEUE_CONNECTION=sync
 * (shared hosting, rule #41), blocking the request well past the web server's
 * timeout. See BackupManagerPageTest's
 * run_now_starts_the_fsm_without_blocking_on_a_dispatched_job regression test.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public string $profile = 'full',
        public string $trigger = 'manual',
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $exitCode = Artisan::call('oeparts:backup', [
            '--profile' => $this->profile,
            '--trigger' => $this->trigger,
        ]);

        // oeparts:backup itself already alerts admins and logs the failure
        // (see RunBackup::handle()) — but discarding its exit code here
        // meant THIS job still reported success to the queue even when the
        // backup failed, hiding it from failed-job monitoring/retry.
        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'oeparts:backup exited with code '.$exitCode.': '.trim(Artisan::output())
            );
        }
    }
}
