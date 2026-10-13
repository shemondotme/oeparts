<?php

namespace App\Console\Commands\Migration;

use App\Models\BackupRun;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\ExportJanitor;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\FreezeReleaser;
use App\Services\Migration\MigrationPaths;
use App\Support\AtomicJsonFile;
use Illuminate\Console\Command;

/**
 * Every five minutes, whenever the scheduler is alive: nothing about an export may depend on a browser tab
 * staying open or a worker staying up.
 *
 *  1. lifts a freeze whose deadline has passed (failing the export it belonged to, if it was still running);
 *  2. gives every export run that nobody has advanced for a couple of minutes one budgeted step, so a live
 *     copy whose tab was closed carries on by itself;
 *  3. fails a run that was started but never picked up by any worker (it froze nothing);
 *  4. while a freeze is held, renews the pause on the customer queues, so a cache flush cannot silently
 *     un-pause them;
 *  5. stamps the time, which is also the scheduler's heartbeat for the migration feature.
 */
class MigrateWatchdog extends Command
{
    protected $signature = 'oeparts:migrate:watchdog';

    protected $description = 'Lift expired export freezes and nudge export runs nobody is driving';

    public function handle(FreezeReleaser $releaser, ExportManager $manager, ExportJanitor $janitor, ExportFreeze $freeze): int
    {
        $released = $releaser->releaseIfExpired();
        if ($released !== null) {
            $this->info("Freeze lifted: {$released}.");
        }

        $idle = max(1, (int) config('migration.export.driver_idle_seconds', 120));
        $nudged = 0;

        foreach (BackupRun::query()->exports()->whereIn('status', [BackupRun::STATUS_PENDING, BackupRun::STATUS_RUNNING])->where('updated_at', '<', now()->subSeconds($idle))->get() as $run) {
            // A pending run that never took a step is the janitor's (no_worker); everything else gets a nudge.
            if ($run->status === BackupRun::STATUS_PENDING && $run->checkpoint()['stage_state'] === [] && $run->checkpoint()['stage_index'] === 0
                && $run->updated_at->lt(now()->subSeconds((int) config('migration.export.worker_pickup_seconds', 120)))) {
                continue;
            }

            $manager->advance($run);
            $nudged++;
        }

        $unpicked = $janitor->failUnpickedRuns();
        $freeze->refreshQueuePauses();

        (new AtomicJsonFile(MigrationPaths::root().DIRECTORY_SEPARATOR.'watchdog.json'))->write(['at' => now()->toIso8601String(), 'nudged' => $nudged, 'released' => $released, 'unpicked' => $unpicked]);

        $this->line("Watchdog: {$nudged} run(s) nudged, {$unpicked} never started.");

        return self::SUCCESS;
    }
}
