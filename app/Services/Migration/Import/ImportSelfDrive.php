<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\MigrationPaths;
use App\Support\AtomicJsonFile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The last-resort driver for a host with no cron at all, called by the 503 gate after it has answered a visitor.
 *
 * It exists so that an import started on such a host still finishes, and it is bounded so that it can never become a way
 * for anonymous traffic to load the server during the 503 window. It does something only when ALL of these hold:
 *
 *   - the scheduler is silent (the watchdog has not stamped its heartbeat lately: if cron works, cron drives);
 *   - the run itself has been idle for a while (nobody is polling it, no other driver is making it move);
 *   - the run's own persisted backoff has passed, and it has not been marked stalled;
 *   - this request wins a NON-blocking file lock (fifty visitors at once: one works, forty-nine go on);
 *
 * and then it does one step of at most a few seconds, whose outcome ImportManager records (a step that moved nothing
 * lengthens the wait; enough of them stall the run). It never decides on its own to roll anything back.
 */
final class ImportSelfDrive
{
    public function __construct(
        private readonly ImportMarker $marker,
        private readonly ImportStateStore $store,
        private readonly ImportManager $manager,
    ) {}

    /** Never throws: it runs after the response was sent and must not turn a 503 into anything else. */
    public function maybeDrive(): void
    {
        try {
            $this->drive();
        } catch (\Throwable $e) {
            Log::channel('migration')->warning('The import self-drive stopped: '.$e->getMessage());
        }
    }

    private function drive(): void
    {
        $runId = $this->marker->runId();

        if ($runId === null) {
            return;
        }

        $state = $this->store->find($runId);

        if ($state === null || $state->status->isTerminal() || $this->manager->isStalled($runId)) {
            return;
        }

        if ($this->schedulerIsAlive() || ! $this->isIdle($state)) {
            return;
        }

        if ($state->nextSelfDriveAt !== null && now()->lt(Carbon::parse($state->nextSelfDriveAt))) {
            return;
        }

        $lock = @fopen($this->store->directory($runId).DIRECTORY_SEPARATOR.'selfdrive.lock', 'c');

        if ($lock === false) {
            return;
        }

        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                return; // another visitor is driving the run right now
            }

            $this->manager->advanceInBackground($runId, $this->budget());
        } finally {
            fclose($lock);
        }
    }

    private function schedulerIsAlive(): bool
    {
        try {
            $stamp = (new AtomicJsonFile(MigrationPaths::root().DIRECTORY_SEPARATOR.'watchdog.json'))->read();
        } catch (\Throwable) {
            return false;
        }

        $at = is_array($stamp) ? (string) ($stamp['at'] ?? '') : '';

        return $at !== '' && Carbon::parse($at)->gt(now()->subSeconds((int) config('migration.import.self_drive.scheduler_fresh_seconds', 600)));
    }

    private function isIdle(ImportState $state): bool
    {
        return Carbon::parse($state->heartbeatAt)->lt(now()->subSeconds((int) config('migration.import.self_drive.heartbeat_seconds', 120)));
    }

    /** At most the configured seconds and never more than half of the web server's own time limit (0 = no limit). */
    private function budget(): float
    {
        $budget = (float) config('migration.import.self_drive.budget_seconds', 10);
        $limit = (float) ini_get('max_execution_time');

        return max(0.0, $limit > 0 ? min($budget, $limit / 2) : $budget);
    }
}
