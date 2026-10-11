<?php

namespace App\Console\Commands;

use App\Models\CleanupRun;
use App\Services\Cleanup\CleanupManager;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Cleanup\Exceptions\CleanupException;
use App\Services\Cleanup\TargetRegistry;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * oeparts:cleanup — run the Cleanup Engine from the command line (the same engine the admin page
 * drives), e.g. from cron, SSH, or ScheduledTasks "Run Now".
 *
 *   php artisan oeparts:cleanup --list
 *   php artisan oeparts:cleanup search_logs cron_logs --dry-run
 *   php artisan oeparts:cleanup                       (every registered, non-report-only target)
 *
 * A run that does not finish within --budget seconds is left resumable: oeparts:cleanup:auto and the
 * stale janitor continue it.
 */
class RunCleanup extends Command
{
    protected $signature = 'oeparts:cleanup
        {targets?* : Target keys to clean (default: all)}
        {--dry-run : Only count what would be cleaned; delete nothing}
        {--budget=50 : Seconds of work before leaving the run to be resumed}
        {--list : List the available targets and exit}';

    protected $description = 'Run the Cleanup Engine on the given targets (or all of them), with verified results';

    public function handle(CleanupManager $manager, TargetRegistry $registry): int
    {
        if ($this->option('list')) {
            $this->table(
                ['Key', 'Group', 'Retention (days)', 'Report only', 'Label'],
                collect($registry->all())->map(fn (CleanupTarget $t) => [
                    $t->key(), $t->group(), $registry->retentionDays($t), $t->reportOnly() ? 'yes' : '', $t->label(),
                ])->values()->all(),
            );

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $keys = (array) $this->argument('targets');
        if ($keys === []) {
            $keys = collect($registry->all())
                ->filter(fn (CleanupTarget $t) => $dry || ! $t->reportOnly())
                ->keys()->all();
        }

        try {
            $run = $manager->start($keys, $dry ? CleanupRun::MODE_DRY_RUN : CleanupRun::MODE_EXECUTE);
        } catch (CleanupException|InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(($dry ? 'Previewing' : 'Cleaning').' '.count($keys)." target(s) — run #{$run->getKey()}");

        $until = microtime(true) + max(1.0, (float) $this->option('budget'));
        $lastPercent = -1;

        while (! $run->isTerminal() && microtime(true) < $until) {
            $progress = $manager->advance($run, min(5.0, max(0.5, $until - microtime(true))));
            $run->refresh();

            if ($progress->percent !== $lastPercent) {
                $lastPercent = $progress->percent;
                $this->line(sprintf('  %3d%%  %s', $progress->percent, $progress->message));
            }

            if ($run->status === CleanupRun::STATUS_PAUSED) {
                $this->warn('Paused: '.$run->message);

                return self::SUCCESS; // not a failure; the scheduler resumes it
            }
        }

        if (! $run->isTerminal()) {
            $this->warn("Out of time budget at {$run->percent}%. The run stays resumable (oeparts:cleanup:auto continues it).");

            return self::SUCCESS;
        }

        foreach ($run->items as $item) {
            $this->line(sprintf(
                '  %-28s %-14s targeted %d, deleted %d, remaining %d%s',
                $item->target, $item->status, $item->targeted, $item->deleted, $item->remaining,
                $item->reasons ? '  ['.implode(', ', $item->reasons).']' : '',
            ));
        }

        $this->{$run->status === CleanupRun::STATUS_SUCCESS ? 'info' : 'warn'}("Run #{$run->getKey()} ended {$run->status}: {$run->message}");

        return $run->status === CleanupRun::STATUS_SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
