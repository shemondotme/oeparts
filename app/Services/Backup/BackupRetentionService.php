<?php

namespace App\Services\Backup;

use App\Models\BackupRun;

/**
 * BackupRetentionService (Module 14/21, Chunk 2.6) — GFS retention pruning
 * (LOCKED DECISION #5): keep the newest successful backup for each of the last
 * N days / weeks / months (config('backup.retention'), default 7 / 4 / 6), and
 * reclaim the files of every other successful backup.
 *
 * Pruned runs KEEP their history row (stamped meta.pruned_at) — only the on-disk
 * parts + manifest are deleted (via the shared BackupJanitor purge). Failed /
 * partial runs are the BackupJanitor's job, not retention's.
 */
class BackupRetentionService
{
    public function __construct(private readonly BackupJanitor $janitor) {}

    /**
     * @return array{kept:int,pruned:int}
     */
    public function prune(): array
    {
        $daily   = max(0, (int) settings('backup.retention_daily', config('backup.retention.daily', 7)));
        $weekly  = max(0, (int) settings('backup.retention_weekly', config('backup.retention.weekly', 4)));
        $monthly = max(0, (int) settings('backup.retention_monthly', config('backup.retention.monthly', 6)));

        // Newest first — the first run seen in each bucket is the one we keep.
        $runs = BackupRun::query()
            ->successful()
            ->whereNull('meta->pruned_at')
            ->orderByRaw('COALESCE(finished_at, started_at, created_at) DESC')
            ->get();

        $keep = [];

        // GFS buckets are scoped PER PROFILE. A slim `update_safety` backup
        // (database only — the update engine's own pre-update safety net,
        // which intentionally omits files) must never compete for the same
        // daily/weekly/monthly slot as a `full` disaster-recovery backup
        // taken the same day: whichever finished later would otherwise win
        // that day's slot and cause the OTHER one's files to be purged —
        // silently losing the only file backup for that retention period if
        // it happened to lose to a same-day update_safety run.
        foreach ($runs->groupBy('profile') as $profileRuns) {
            $days   = [];
            $weeks  = [];
            $months = [];

            foreach ($profileRuns as $run) {
                $at = $run->finished_at ?? $run->started_at ?? $run->created_at;
                if (! $at) {
                    $keep[$run->getKey()] = true; // undateable — never auto-prune
                    continue;
                }

                $day   = $at->format('Y-m-d');
                $week  = $at->format('o-W');
                $month = $at->format('Y-m');

                if (! isset($days[$day]) && count($days) < $daily) {
                    $days[$day] = true;
                    $keep[$run->getKey()] = true;
                }
                if (! isset($weeks[$week]) && count($weeks) < $weekly) {
                    $weeks[$week] = true;
                    $keep[$run->getKey()] = true;
                }
                if (! isset($months[$month]) && count($months) < $monthly) {
                    $months[$month] = true;
                    $keep[$run->getKey()] = true;
                }
            }
        }

        $pruned = 0;

        foreach ($runs as $run) {
            if (isset($keep[$run->getKey()])) {
                continue;
            }

            if (! $this->janitor->purgeFiles($run)) {
                // Leave meta.pruned_at unset — the whereNull('meta->pruned_at')
                // filter above means this run is reconsidered on the next
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

        return ['kept' => count($keep), 'pruned' => $pruned];
    }
}
