<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use App\Models\UpdateHistory;
use Illuminate\Support\Collection;

/**
 * BackupRetentionService (Module 14/21, Chunk 2.6) — GFS retention pruning
 * (LOCKED DECISION #5): keep the newest successful backup for each of the last
 * N days / weeks / months (config('backup.retention'), default 7 / 4 / 6), and
 * reclaim the files of every other successful backup.
 *
 * Pruned runs KEEP their history row (stamped meta.pruned_at) — only the on-disk
 * parts + manifest are deleted (via the shared BackupJanitor purge). Failed /
 * partial runs are the BackupJanitor's job, not retention's.
 *
 * plan() is the pure decision (no deletion) so the same logic feeds both the real
 * prune() and any "what would be deleted?" preview. On top of the GFS buckets it
 * applies safety guards, because retention settings are operator-editable and a
 * bad value must never be able to destroy the only usable restore point.
 */
class BackupRetentionService
{
    public const REASON_GFS = 'gfs';

    /** The newest successful run of each profile — never pruned, whatever the retention numbers say. */
    public const REASON_NEWEST = 'newest_of_profile';

    /** Referenced by an update/restore that is in flight (or the latest one): rollback needs it. */
    public const REASON_UPDATE = 'update_referenced';

    /** Incremental file backups chain through earlier runs, so none of the profile can go. */
    public const REASON_INCREMENTAL = 'incremental_chain';

    public const REASON_UNDATEABLE = 'undateable';

    public function __construct(private readonly BackupJanitor $janitor) {}

    /**
     * Decide what to keep and what to prune, without touching anything.
     *
     * @return array{keep: array<int, list<string>>, prune: Collection<int, BackupRun>} keep maps run id => why
     */
    public function plan(): array
    {
        $daily = max(0, (int) settings('backup.retention_daily', config('backup.retention.daily', 7)));
        $weekly = max(0, (int) settings('backup.retention_weekly', config('backup.retention.weekly', 4)));
        $monthly = max(0, (int) settings('backup.retention_monthly', config('backup.retention.monthly', 6)));

        // Newest first — the first run seen in each bucket is the one we keep.
        $runs = BackupRun::query()
            ->successful()
            ->whereNull('meta->pruned_at')
            ->orderByRaw('COALESCE(finished_at, started_at, created_at) DESC')
            ->get();

        /** @var array<int, list<string>> $keep */
        $keep = [];
        $mark = function (int|string $id, string $reason) use (&$keep): void {
            $keep[(int) $id][] = $reason;
        };

        // GFS buckets are scoped PER PROFILE. A slim `update_safety` backup
        // (database only — the update engine's own pre-update safety net,
        // which intentionally omits files) must never compete for the same
        // daily/weekly/monthly slot as a `full` disaster-recovery backup
        // taken the same day: whichever finished later would otherwise win
        // that day's slot and cause the OTHER one's files to be purged —
        // silently losing the only file backup for that retention period if
        // it happened to lose to a same-day update_safety run.
        foreach ($runs->groupBy('profile') as $profileRuns) {
            $days = [];
            $weeks = [];
            $months = [];

            // Guard: the newest successful run of a profile is the only thing standing
            // between the operator and "no backup at all". Retention of 0/0/0 (or a
            // bucket quirk) must not be able to prune it.
            $newest = $profileRuns->first();
            if ($newest) {
                $mark($newest->getKey(), self::REASON_NEWEST);
            }

            // Guard: incremental file backups store unchanged files as references to
            // earlier runs' volumes. Pruning any run could orphan the ones after it.
            if ((bool) config('backup.files.incremental', false)
                || $profileRuns->contains(fn (BackupRun $r) => (bool) (($r->meta ?? [])['incremental'] ?? false))) {
                foreach ($profileRuns as $run) {
                    $mark($run->getKey(), self::REASON_INCREMENTAL);
                }
            }

            foreach ($profileRuns as $run) {
                $at = $run->finished_at ?? $run->started_at ?? $run->created_at;
                if (! $at) {
                    $mark($run->getKey(), self::REASON_UNDATEABLE); // undateable — never auto-prune

                    continue;
                }

                $day = $at->format('Y-m-d');
                $week = $at->format('o-W');
                $month = $at->format('Y-m');

                if (! isset($days[$day]) && count($days) < $daily) {
                    $days[$day] = true;
                    $mark($run->getKey(), self::REASON_GFS);
                }
                if (! isset($weeks[$week]) && count($weeks) < $weekly) {
                    $weeks[$week] = true;
                    $mark($run->getKey(), self::REASON_GFS);
                }
                if (! isset($months[$month]) && count($months) < $monthly) {
                    $months[$month] = true;
                    $mark($run->getKey(), self::REASON_GFS);
                }
            }
        }

        foreach ($this->updateReferencedRunIds() as $id) {
            $mark($id, self::REASON_UPDATE);
        }

        $prune = $runs->reject(fn (BackupRun $run) => isset($keep[$run->getKey()]))->values();

        return [
            'keep' => array_map(fn (array $reasons) => array_values(array_unique($reasons)), $keep),
            'prune' => $prune,
        ];
    }

    /**
     * @return array{kept:int,pruned:int}
     */
    public function prune(): array
    {
        $plan = $this->plan();

        $pruned = 0;

        foreach ($plan['prune'] as $run) {
            if (! $this->janitor->purgeFiles($run)) {
                // Leave meta.pruned_at unset — the whereNull('meta->pruned_at')
                // filter in plan() means this run is reconsidered on the next
                // prune() call, so a failed deletion is retried instead of
                // silently leaking storage forever.
                continue;
            }

            $meta = $run->meta ?? [];
            $meta['pruned_at'] = now()->toIso8601String();
            $run->meta = $meta;
            $run->save();

            $pruned++;
        }

        return ['kept' => count($plan['keep']), 'pruned' => $pruned];
    }

    /**
     * Backup runs an update/restore may still need to roll back to: every one
     * referenced by an unfinished update/restore, plus the ones referenced by the
     * most recent update and the most recent restore (a finished update can still be
     * rolled back from the Recovery Console). update_histories has no FK to
     * backup_runs, so nothing in the database stops retention deleting these.
     *
     * Public so the Cleanup Engine's "old backup history rows" target can protect the same runs.
     *
     * @return list<int>
     */
    public function updateReferencedRunIds(): array
    {
        $rows = UpdateHistory::query()
            ->whereNotIn('status', [
                UpdateHistory::STATUS_SUCCESS,
                UpdateHistory::STATUS_FAILED,
                UpdateHistory::STATUS_ROLLED_BACK,
            ])
            ->get();

        foreach ([UpdateHistory::TYPE_UPDATE, UpdateHistory::TYPE_RESTORE] as $type) {
            $latest = UpdateHistory::query()->where('type', $type)->orderByDesc('id')->first();
            if ($latest) {
                $rows->push($latest);
            }
        }

        $ids = [];
        foreach ($rows as $history) {
            foreach ([
                $history->backup_run_id,
                $history->restore_of_backup_run_id,
                ($history->meta ?? [])['safety_backup_run_id'] ?? null,
            ] as $id) {
                if ($id) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
