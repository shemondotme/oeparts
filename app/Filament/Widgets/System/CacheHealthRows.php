<?php

namespace App\Filament\Widgets\System;

use App\Filament\Widgets\Concerns\HasMonitoringVisuals;
use App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
use App\Models\CacheMetricSnapshot;
use App\Services\CacheMetricsService;
use Filament\Widgets\Widget;

/**
 * Dense ops row-list for the Cache Dashboard's "Server Health" section — same
 * pattern as App\Filament\Widgets\System\HealthCheckStats (a plain Widget,
 * not StatsOverviewWidget, for the same reason: pills/sparklines/left-accent
 * rows aren't Stat::make()-expressible). Category Breakdown and the Key
 * Browser live directly on CacheDashboard itself, not here — see that page.
 */
class CacheHealthRows extends Widget
{
    use HasMonitoringVisuals;
    use InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.system.cache-health-rows';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        $health = $this->cachedHealthData('rows', fn () => app(CacheMetricsService::class)->snapshot());

        $history = CacheMetricSnapshot::query()
            ->orderByDesc('recorded_at')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        $lastRecordedAt = $history->last()?->recorded_at?->diffForHumans() ?? 'never';

        return [
            $this->hitRateRow($health, $history, $lastRecordedAt),
            $this->memoryRow($health, $history, $lastRecordedAt),
            $this->persistenceRow($health, $lastRecordedAt),
            $this->throughputRow($health, $history, $lastRecordedAt),
            $this->evictionsRow($health, $history, $lastRecordedAt),
            $this->totalKeysRow($health, $history, $lastRecordedAt),
            $this->uptimeRow($health, $lastRecordedAt),
        ];
    }

    private function hitRateRow(array $health, $history, string $lastRecordedAt): array
    {
        $color = $this->hitRateColor($health['hit_rate']);

        return [
            'key' => 'hit_rate',
            'label' => 'Hit Rate',
            'sub' => 'last 30s window',
            'status' => $color,
            'pill' => $health['hit_rate'] . '%',
            'state' => $this->colorToState($color),
            'detail' => number_format($health['hits']) . ' hits',
            'latency' => number_format($health['misses']) . ' misses',
            'sparkline' => $history->map(fn ($s) => $this->colorToState($this->hitRateColor($s->hit_rate)))->all(),
            'lastChecked' => $lastRecordedAt,
            'url' => null,
        ];
    }

    private function memoryRow(array $health, $history, string $lastRecordedAt): array
    {
        $pct = $health['memory_used_pct'];
        $color = $pct === null ? 'gray' : ($pct < 70 ? 'success' : ($pct < 90 ? 'warning' : 'danger'));

        return [
            'key' => 'memory',
            'label' => 'Memory',
            'sub' => 'used / max',
            'status' => $color,
            'pill' => $pct !== null ? "{$pct}%" : $health['memory_used_human'],
            'state' => $this->colorToState($color),
            'detail' => $health['memory_used_human'],
            'latency' => 'peak ' . $health['memory_peak_human'] . ($health['fragmentation_ratio'] ? ' · frag ' . $health['fragmentation_ratio'] . 'x' : ''),
            'sparkline' => $history->map(fn () => 'muted')->all(),
            'lastChecked' => $lastRecordedAt,
            'url' => null,
        ];
    }

    private function persistenceRow(array $health, string $lastRecordedAt): array
    {
        $color = $health['rdb_last_bgsave_ok'] ? 'success' : 'danger';
        $lastSave = $health['rdb_last_save_at'] ? now()->setTimestamp($health['rdb_last_save_at'])->diffForHumans() : 'never';

        return [
            'key' => 'persistence',
            'label' => 'Persistence',
            'sub' => 'RDB snapshotting',
            'status' => $color,
            'pill' => $health['rdb_last_bgsave_ok'] ? 'Saved' : 'Failed',
            'state' => $this->colorToState($color),
            'detail' => "last save {$lastSave}",
            'latency' => $health['aof_enabled'] ? 'AOF enabled' : null,
            'sparkline' => [],
            'lastChecked' => $lastRecordedAt,
            'url' => null,
        ];
    }

    private function throughputRow(array $health, $history, string $lastRecordedAt): array
    {
        return [
            'key' => 'throughput',
            'label' => 'Throughput',
            'sub' => 'ops / sec',
            'status' => 'gray',
            'pill' => $health['ops_per_sec'] !== null ? number_format($health['ops_per_sec']) . '/s' : '—',
            'state' => 'muted',
            'detail' => $health['connected_clients'] . ' connected clients',
            'latency' => null,
            'sparkline' => $history->map(fn () => 'muted')->all(),
            'lastChecked' => $lastRecordedAt,
            'url' => null,
        ];
    }

    private function evictionsRow(array $health, $history, string $lastRecordedAt): array
    {
        $color = $health['evicted_keys'] > 0 ? 'warning' : 'success';

        return [
            'key' => 'evictions',
            'label' => 'Evictions',
            'sub' => 'policy: ' . $health['maxmemory_policy'],
            'status' => $color,
            'pill' => number_format($health['evicted_keys']) . ' evicted',
            'state' => $this->colorToState($color),
            'detail' => 'since last restart',
            'latency' => $health['evicted_keys'] > 0 ? 'consider raising maxmemory' : null,
            'sparkline' => $history->map(fn ($s) => $this->colorToState($s->evicted_keys > 0 ? 'warning' : 'success'))->all(),
            'lastChecked' => $lastRecordedAt,
            'url' => null,
        ];
    }

    private function totalKeysRow(array $health, $history, string $lastRecordedAt): array
    {
        return [
            'key' => 'total_keys',
            'label' => 'Total Keys',
            'sub' => 'cache database',
            'status' => 'gray',
            'pill' => number_format($health['total_keys']),
            'state' => 'muted',
            'detail' => 'application cache keys',
            'latency' => null,
            'sparkline' => $history->map(fn () => 'muted')->all(),
            'lastChecked' => $lastRecordedAt,
            'url' => null,
        ];
    }

    private function uptimeRow(array $health, string $lastRecordedAt): array
    {
        return [
            'key' => 'uptime',
            'label' => 'Uptime',
            'sub' => 'since last restart',
            'status' => 'gray',
            'pill' => $this->formatUptime($health['uptime_seconds']),
            'state' => 'muted',
            'detail' => 'Redis ' . $health['redis_version'],
            'latency' => null,
            'sparkline' => [],
            'lastChecked' => $lastRecordedAt,
            'url' => null,
        ];
    }

    private function hitRateColor(float|int $rate): string
    {
        return $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
    }

    private function formatUptime(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);

        if ($days > 0) {
            return "{$days}d {$hours}h";
        }

        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }
}
