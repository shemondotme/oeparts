<?php

namespace App\Console\Commands\Migration;

use App\Models\BackupRun;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\ExportJanitor;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\FreezeReleaser;
use App\Services\Migration\Import\ImportWatchdog;
use App\Services\Migration\MigrationPaths;
use App\Support\AtomicJsonFile;
use Illuminate\Console\Command;

/**
 * Every five minutes, whenever the scheduler is alive: nothing about an export may depend on a browser tab
 * staying open or a worker staying up.
 *
 *  1. lifts a freeze whose deadline has passed (failing the export it belonged to, if it was still running);
 *  2. drives every export run that nobody has advanced for a couple of minutes, step after step for the visit's
 *     time budget, so a live copy whose tab was closed carries on by itself and finishes in minutes, not hours;
 *  3. fails a run that was started but never picked up by any worker (it froze nothing);
 *  4. while a freeze is held, renews the pause on the customer queues, so a cache flush cannot silently
 *     un-pause them;
 *  5. nudges import runs nobody is driving and marks the ones that went quiet (ImportWatchdog);
 *  6. stamps the time, which is also the scheduler's heartbeat for the migration feature.
 */
class MigrateWatchdog extends Command
{
    protected $signature = 'oeparts:migrate:watchdog';

    protected $description = 'Lift expired export freezes and keep export runs nobody is driving moving';

    public function handle(FreezeReleaser $releaser, ExportManager $manager, ExportJanitor $janitor, ExportFreeze $freeze, ImportWatchdog $imports): int
    {
        $released = $releaser->releaseIfExpired();
        if ($released !== null) {
            $this->info("Freeze lifted: {$released}.");
        }

        $idle = max(1, (int) config('migration.export.driver_idle_seconds', 120));
        $nudged = 0;
        $budgetEnds = microtime(true) + max(0, (int) config('migration.export.watchdog_budget_seconds', 240));

        foreach (BackupRun::query()->exports()->whereIn('status', [BackupRun::STATUS_PENDING, BackupRun::STATUS_RUNNING])->where('updated_at', '<', now()->subSeconds($idle))->get() as $run) {
            // A pending run that never took a step is the janitor's (no_worker); everything else gets a nudge.
            if ($run->status === BackupRun::STATUS_PENDING && $run->checkpoint()['stage_state'] === [] && $run->checkpoint()['stage_index'] === 0
                && $run->updated_at->lt(now()->subSeconds((int) config('migration.export.worker_pickup_seconds', 120)))) {
                continue;
            }

            $this->drive($manager, $run, $budgetEnds);
            $nudged++;
        }

        $unpicked = $janitor->failUnpickedRuns();
        $freeze->refreshQueuePauses();

        // The import half: runs nobody has advanced for a couple of minutes carry on, runs that have gone quiet for a long time are marked.
        $import = $imports->run($budgetEnds);

        (new AtomicJsonFile(MigrationPaths::root().DIRECTORY_SEPARATOR.'watchdog.json'))->write(['at' => now()->toIso8601String(), 'nudged' => $nudged, 'released' => $released, 'unpicked' => $unpicked, 'imports_nudged' => $import['nudged'], 'imports_stalled' => $import['stalled']]);

        $this->line("Watchdog: {$nudged} export run(s) nudged, {$unpicked} never started; {$import['nudged']} import run(s) nudged, {$import['stalled']} stalled.");

        return self::SUCCESS;
    }

    /**
     * One step is a few seconds of work and this command comes round every five minutes, so a single step per
     * visit would take hours to finish an export nobody else is driving. It keeps stepping until the run ends or
     * this visit's time budget (less than the interval) is spent, then leaves the rest to the next visit. Another
     * driver that wakes up meanwhile is harmless: only one process can advance a run at a time.
     */
    private function drive(ExportManager $manager, BackupRun $run, float $budgetEnds): void
    {
        do {
            $before = json_encode($run->checkpoint());
            $progress = $manager->advance($run);
            $run->refresh();

            if ($progress->done || $run->isTerminal()) {
                return;
            }

            if (json_encode($run->checkpoint()) === $before) {
                usleep(250_000); // nothing moved: somebody else holds the run for now, do not spin on it
            }
        } while (microtime(true) < $budgetEnds);
    }
}
