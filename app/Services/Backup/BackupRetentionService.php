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
        $daily   = max(0, (int) config('backup.retention.daily', 7));
        $weekly  = max(0, (int) config('backup.retention.weekly', 4));
        $monthly = max(0, (int) config('backup.retention.monthly', 6));

        // Newest first — the first run seen in each bucket is the one we keep.
        $runs = BackupRun::query()
            ->successful()
            ->whereNull('meta->pruned_at')
            ->orderByRaw('COALESCE(finished_at, started_at, created_at) DESC')
            ->get();

        $keep   = [];
        $days   = [];
        $weeks  = [];
        $months = [];

        foreach ($runs as $run) {
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

        $pruned = 0;

        foreach ($runs as $run) {
            if (isset($keep[$run->getKey()])) {
                continue;
            }

            $this->janitor->purgeFiles($run);

            $meta = $run->meta ?? [];
            $meta['pruned_at'] = now()->toIso8601String();
            $run->meta = $meta;
            $run->save();

            $pruned++;
        }

        return ['kept' => count($keep), 'pruned' => $pruned];
    }
}
