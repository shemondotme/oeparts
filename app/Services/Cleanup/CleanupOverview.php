<?php

namespace App\Services\Cleanup;

use App\Models\CleanupDiskSample;
use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Models\CleanupTargetStat;
use App\Services\Cleanup\Contracts\CleanupTarget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the Cleanup page DISPLAYS, assembled from cached tables only — no COUNT(*), no directory walk, a
 * handful of queries however many targets there are. (The numbers are filled in by CleanupMeasurer.)
 */
final class CleanupOverview
{
    public function __construct(
        private readonly TargetRegistry $registry,
        private readonly DiskUsageService $disk,
    ) {}

    /**
     * Disk state now, plus what the last sample says about where it went and how fast it grows.
     *
     * @return array{total:int|null, free:int|null, used:int|null, used_pct:float|null, level:string, warn_pct:int, fail_pct:int,
     *               breakdown:array<string, array{bytes:int, complete:bool}>, sampled_at:Carbon|null,
     *               growth_bytes:int|null, growth_days:int|null}
     */
    public function disk(): array
    {
        $now = $this->disk->snapshot();
        $latest = CleanupDiskSample::query()->orderByDesc('measured_at')->first();

        // Growth: compare now with the oldest sample from the last 30 days (a trend needs a baseline).
        $baseline = CleanupDiskSample::query()
            ->where('measured_at', '>=', now()->subDays(30))
            ->orderBy('measured_at')
            ->first();

        $growth = null;
        $growthDays = null;
        if ($baseline !== null && $now['used'] !== null && $baseline->measured_at->lt(now()->subDay())) {
            $growth = $now['used'] - $baseline->used_bytes;
            $growthDays = max(1, (int) round($baseline->measured_at->diffInDays(now())));
        }

        return [
            'total' => $now['total'],
            'free' => $now['free'],
            'used' => $now['used'],
            'used_pct' => $now['used_pct'],
            'level' => $this->disk->level(),
            'warn_pct' => (int) settings('cleanup.disk_alert_pct', config('cleanup.disk_alert_pct', 80)),
            'fail_pct' => (int) config('cleanup.disk_fail_pct', 95),
            'breakdown' => $latest !== null ? ($latest->breakdown ?? []) : [],
            'sampled_at' => $latest?->measured_at,
            'growth_bytes' => $growth,
            'growth_days' => $growthDays,
        ];
    }

    /**
     * One display row per registered target, keyed by target key, in registration order.
     *
     * @return array<string, array<string, mixed>>
     */
    public function targets(): array
    {
        $stats = CleanupTargetStat::query()->get()->keyBy('target');
        $last = $this->lastCleaned();
        $rows = [];

        foreach ($this->registry->all() as $key => $target) {
            $stat = $stats->get($key);
            $item = $last[$key] ?? null;

            $rows[$key] = [
                'key' => $key,
                'label' => $target->label(),
                'description' => $target->description(),
                'group' => $target->group(),
                'kind' => $target->kind(),
                'report_only' => $target->reportOnly(),
                'truncatable' => $target->truncatable(),
                'age_based' => $target->defaultDays() > 0,
                'days' => $this->registry->retentionDays($target),
                'default_days' => $target->defaultDays(),
                'floor_days' => $target->floorDays(),
                'auto' => $this->registry->autoEnabled($target),
                'needs_lock' => $target->needsBackupLock(),
                'rows' => $stat?->rows_est,
                'bytes' => $stat?->bytes_est,
                'oldest' => $stat?->oldest_at,
                'eligible_rows' => $stat?->eligible_rows,
                'eligible_bytes' => $stat?->eligible_bytes_est,
                'measured_at' => $stat?->measured_at,
                'last_status' => $item?->status,
                'last_at' => $item?->finished_at,
                'last_deleted' => $item?->deleted,
                'last_bytes' => $item !== null ? ($item->bytes_freed_allocated ?? $item->bytes_freed_apparent) : null,
            ];
        }

        return $rows;
    }

    /** @return array<string, array<string, mixed>> */
    public function targetsInGroups(array $groups): array
    {
        return array_filter($this->targets(), fn (array $row) => in_array($row['group'], $groups, true));
    }

    /** Sum of every known "eligible now" size: what a full cleanup would give back. */
    public function reclaimableBytes(): int
    {
        return (int) CleanupTargetStat::query()->sum('eligible_bytes_est');
    }

    /** @return Collection<int, CleanupRun> */
    public function recentRuns(int $limit): Collection
    {
        return CleanupRun::query()->with('admin:id,name')->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * The most recent real (non-preview) item per target that actually cleaned something or tried to.
     *
     * @return array<string, CleanupRunItem>
     */
    private function lastCleaned(): array
    {
        $latestIds = CleanupRunItem::query()
            ->join('cleanup_runs', 'cleanup_runs.id', '=', 'cleanup_run_items.run_id')
            ->where('cleanup_runs.mode', CleanupRun::MODE_EXECUTE)
            ->whereIn('cleanup_run_items.status', [CleanupRunItem::STATUS_SUCCESS, CleanupRunItem::STATUS_PARTIAL, CleanupRunItem::STATUS_FAILED])
            ->selectRaw('MAX(cleanup_run_items.id) as id')
            ->groupBy('cleanup_run_items.target')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return [];
        }

        return CleanupRunItem::query()->whereIn('id', $latestIds)->get()->keyBy('target')->all();
    }

    public function target(string $key): CleanupTarget
    {
        return $this->registry->get($key);
    }
}
