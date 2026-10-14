<?php

namespace App\Services\Migration\Import;

use Carbon\Carbon;

/**
 * The primary background driver of an import: the scheduler calls it every five minutes. For every run that is still alive
 * in the books (not terminal) it does two things:
 *
 *   - a run whose heartbeat is older than the stalled age (and that did not announce a long statement) is marked stalled;
 *   - a run that nobody has advanced for a couple of minutes gets stepped, again and again until this visit's budget is
 *     spent, so an import whose browser tab was closed carries on by itself instead of waiting for the next visit.
 *
 * It looks at runs, not at the marker: a run whose marker is gone but whose last stage is unfinished is still resumed. The
 * attempt cap and backoff are ImportManager's (shared with the gate's self-drive), and another driver waking up meanwhile is
 * harmless: only one process can advance a run at a time.
 */
final class ImportWatchdog
{
    public function __construct(private readonly ImportStateStore $store, private readonly ImportManager $manager) {}

    /**
     * @return array{nudged: int, stalled: int, steps: int}
     */
    public function run(?float $budgetEnds = null): array
    {
        $budgetEnds ??= microtime(true) + max(0, (int) config('migration.import.watchdog_budget_seconds', 240));
        $idle = max(1, (int) config('migration.import.watchdog_idle_seconds', 120));
        $stalledAfter = max($idle, (int) config('migration.import.watchdog_stalled_seconds', 900));
        $nudged = 0;
        $stalled = 0;
        $steps = 0;

        foreach ($this->store->all() as $state) {
            if ($state->status->isTerminal() || ($state->status === ImportStatus::AwaitingConfirmation && ! $state->flag('confirmed'))) {
                continue;
            }

            if (! $state->isAlive($stalledAfter)) {
                $this->manager->markStalled($state->id);
                $stalled++;
            }

            if (Carbon::parse($state->heartbeatAt)->gte(now()->subSeconds($idle))) {
                continue; // somebody is driving it
            }

            $nudged++;

            do {
                $progress = $this->manager->advanceInBackground($state->id, max(0.0, min((float) config('migration.import.self_drive.budget_seconds', 10), $budgetEnds - microtime(true))));
                $steps++;
            } while (($progress['advanced'] ?? false) === true
                && ! ($progress['terminal'] ?? false)
                && ! ($progress['stalled'] ?? false)
                && ($progress['status'] ?? '') !== 'awaiting_confirmation'
                && microtime(true) < $budgetEnds);
        }

        return ['nudged' => $nudged, 'stalled' => $stalled, 'steps' => $steps];
    }
}
