<?php

namespace App\Services\Cleanup;

use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Cleanup\Exceptions\CleanupBusyException;
use Illuminate\Support\Facades\Log;

/**
 * What `oeparts:cleanup:auto` does on each tick (every 15 minutes). Cheap by design: when there is
 * nothing to do it returns after a couple of indexed queries.
 *
 *  1. A run that nobody is driving (heartbeat older than 90 s: the browser was closed, the CLI
 *     process was cut off) is advanced — whether it was started by hand or by the scheduler.
 *  2. Otherwise, if automatic cleanup is ON, the window is open and the system is idle, the targets
 *     that are due start as one batch.
 *
 * Safety rule: the FIRST automatic run of a target is always a dry run. Real deletion only starts once
 * a preview has completed (or an admin has run it by hand), so turning auto-clean on can never delete
 * on its very first night. A target is "due" once it has not had an executed automatic run for 20 hours.
 */
final class CleanupScheduler
{
    /** A run touched more recently than this is being driven by someone (a polling browser). */
    private const DRIVER_GRACE_SECONDS = 90;

    private const DUE_AFTER_HOURS = 20;

    public function __construct(
        private readonly CleanupManager $manager,
        private readonly TargetRegistry $registry,
        private readonly CleanupWindow $window,
    ) {}

    /**
     * @return string what happened: 'advanced' | 'has_driver' | 'disabled' | 'outside_window' | 'busy' | 'nothing_due' | 'started_dry_run' | 'started'
     */
    public function tick(?float $budgetSeconds = null): string
    {
        $budget = $budgetSeconds ?? (float) config('cleanup.cli_tick_seconds', 50);

        $active = CleanupRun::query()->active()->orderBy('id')->first();
        if ($active !== null) {
            if ($active->updated_at->gt(now()->subSeconds(self::DRIVER_GRACE_SECONDS))) {
                return 'has_driver';
            }

            $this->manager->advance($active, $budget);

            return 'advanced';
        }

        if (! (bool) settings('cleanup.auto_enabled', config('cleanup.auto_enabled', false))) {
            return 'disabled';
        }

        if (! $this->window->isOpen()) {
            return 'outside_window';
        }

        $due = array_values(array_filter(
            $this->registry->all(),
            fn (CleanupTarget $t) => $this->registry->autoEnabled($t) && $this->isDue($t),
        ));
        if ($due === []) {
            return 'nothing_due';
        }

        $unarmed = array_values(array_filter($due, fn (CleanupTarget $t) => ! $this->isArmed($t)));
        $mode = $unarmed !== [] ? CleanupRun::MODE_DRY_RUN : CleanupRun::MODE_EXECUTE;
        $targets = $unarmed !== [] ? $unarmed : $due;

        try {
            $run = $this->manager->start(
                array_map(fn (CleanupTarget $t) => $t->key(), $targets),
                $mode,
                CleanupRun::TRIGGER_SCHEDULED,
            );
        } catch (CleanupBusyException $e) {
            Log::channel('cleanup')->info('Automatic cleanup postponed: '.$e->getMessage());

            return 'busy';
        }

        $this->manager->run($run, $budget, $budget);

        return $mode === CleanupRun::MODE_DRY_RUN ? 'started_dry_run' : 'started';
    }

    /** No executed automatic run for this target in the last DUE_AFTER_HOURS. */
    private function isDue(CleanupTarget $target): bool
    {
        $last = CleanupRunItem::query()
            ->where('target', $target->key())
            ->whereIn('status', [
                CleanupRunItem::STATUS_SUCCESS, CleanupRunItem::STATUS_PARTIAL, CleanupRunItem::STATUS_FAILED,
                CleanupRunItem::STATUS_SKIPPED_GUARD, CleanupRunItem::STATUS_SKIPPED_FLOOR,
            ])
            ->whereHas('run', fn ($q) => $q->where('trigger', CleanupRun::TRIGGER_SCHEDULED)->where('mode', CleanupRun::MODE_EXECUTE))
            ->orderByDesc('finished_at')
            ->first();

        return $last === null || $last->finished_at === null || $last->finished_at->lt(now()->subHours(self::DUE_AFTER_HOURS));
    }

    /**
     * A target is armed for real deletion once a preview of it succeeded, or it was already cleaned
     * for real (e.g. by hand) — i.e. somebody has seen what it would do.
     */
    private function isArmed(CleanupTarget $target): bool
    {
        $previewed = CleanupRunItem::query()
            ->where('target', $target->key())
            ->where('status', CleanupRunItem::STATUS_SUCCESS)
            ->whereHas('run', fn ($r) => $r->where('mode', CleanupRun::MODE_DRY_RUN))
            ->exists();

        if ($previewed) {
            return true;
        }

        return CleanupRunItem::query()
            ->where('target', $target->key())
            ->whereIn('status', [CleanupRunItem::STATUS_SUCCESS, CleanupRunItem::STATUS_PARTIAL])
            ->whereHas('run', fn ($r) => $r->where('mode', CleanupRun::MODE_EXECUTE))
            ->exists();
    }
}
