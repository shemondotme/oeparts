<?php

namespace App\Services\Cleanup;

use App\Models\CleanupRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Last line of defence for runs whose driver disappeared: a browser tab closed mid-run, a worker
 * killed by the host, a request cut off by the shared host's time limit. Modelled on
 * BackupJanitor / UpdateWatchdog and run hourly.
 *
 * A run whose heartbeat (updated_at) is older than `stale_after_seconds` is first RE-DRIVEN from its
 * checkpoint — most of the time that is all it needs. Only a run that makes no progress for a long
 * time is closed (PARTIAL if it had already deleted something, else FAILED), which also frees the
 * unique active key and the shared lock so cleanup, backups and updates are never blocked by a dead run.
 *
 * "Progress" is `meta.last_progress_at`, not updated_at: a paused run keeps its heartbeat fresh while it
 * waits, but waiting is not progress.
 */
final class CleanupJanitor
{
    /** A run waiting for the automatic window / an idle system is allowed to wait much longer than a stuck one. */
    private const PAUSED_PATIENCE_SECONDS = 48 * 3600;

    public function __construct(private readonly CleanupManager $manager) {}

    /**
     * @return int number of runs that ended up terminal (finished by the re-drive, or abandoned)
     */
    public function reclaimStale(): int
    {
        $staleAfter = (int) settings('cleanup.stale_after_seconds', config('cleanup.stale_after_seconds', 900));
        $abandonAfter = (int) config('cleanup.abandon_after_seconds', 6 * 3600);
        $reclaimed = 0;

        $stale = CleanupRun::query()->active()->where('updated_at', '<', now()->subSeconds($staleAfter))->get();

        foreach ($stale as $run) {
            try {
                $this->manager->advance($run, 5.0);
                $run->refresh();
            } catch (Throwable $e) {
                Log::channel('cleanup')->error("Janitor could not re-drive run {$run->getKey()}: ".$e->getMessage(), ['exception' => $e]);
            }

            if ($run->isTerminal()) {
                $reclaimed++;

                continue;
            }

            $last = $run->getMetaValue('last_progress_at');
            $lastProgress = $last ? Carbon::parse($last) : ($run->started_at ?? $run->created_at);
            $patience = $run->status === CleanupRun::STATUS_PAUSED ? self::PAUSED_PATIENCE_SECONDS : $abandonAfter;

            if ($lastProgress->lt(now()->subSeconds($patience))) {
                $this->manager->abandon(
                    $run,
                    'No progress for '.$lastProgress->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE).'; the run was closed by the janitor and can be resumed.',
                    ['heartbeat_lost'],
                );
                $reclaimed++;
            }
        }

        return $reclaimed;
    }
}
