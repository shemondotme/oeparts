<?php

namespace App\Services\Migration\Export;

use App\Models\BackupRun;
use App\Services\Migration\MigrationQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Drives an export from a queue worker, ONE budgeted advance at a time. After each advance the job puts
 * itself back on the queue with a short delay while the run is not finished, so it never holds a worker for
 * hours and a worker restart loses at most one step. It runs on the migration queue, which a freeze never
 * pauses (the customer queues are the ones it pauses).
 *
 * `tries = 1`: a failed step is not retried blindly. The run's own state machine decides what a failure
 * means (the manager fails the run), and the watchdog nudges a run nobody is advancing.
 *
 * `timeout` is deliberately generous (the stages keep each step to the configured batch time, but one
 * packaging entry is a whole volume), and the worker's own `--timeout` must not be lower; the default
 * supervisor template has none, and the compose worker does not set one.
 */
class RunExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $runId)
    {
        $this->onQueue(MigrationQueues::migration());
    }

    public function handle(ExportManager $manager): void
    {
        $run = BackupRun::query()->exports()->whereKey($this->runId)->first();

        if ($run === null || $run->isTerminal()) {
            return;
        }

        $progress = $manager->advance($run);

        if (! $progress->done) {
            self::dispatch($this->runId)->delay(now()->addSeconds(max(0, (int) config('migration.export.redispatch_delay_seconds', 1))));
        }
    }

    public function failed(\Throwable $e): void
    {
        $run = BackupRun::query()->exports()->whereKey($this->runId)->first();

        if ($run !== null && ! $run->isTerminal()) {
            app(ExportManager::class)->fail($run, 'The export job crashed: '.$e->getMessage());
        }
    }
}
