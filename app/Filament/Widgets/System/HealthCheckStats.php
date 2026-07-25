<?php

namespace App\Filament\Widgets\System;

use App\Filament\Pages\System\BackupDashboard;
use App\Filament\Pages\System\CacheDashboard;
use App\Filament\Pages\System\QueueMonitor;
use App\Filament\Pages\System\ScheduledTasksPage;
use App\Filament\Pages\System\ServerMonitor;
use App\Filament\Widgets\Concerns\HasMonitoringVisuals;
use App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
use App\Models\HealthCheckSnapshot;
use App\Services\HealthCheckService;
use Filament\Widgets\Widget;

/**
 * Dense ops row-list for the seven admin health checks — a plain Widget (not
 * StatsOverviewWidget) because the row layout (status dot, pill, detail,
 * inline sparkline, relative last-checked time, whole-row click-through)
 * isn't expressible through Stat::make(). Same pattern as GroupHeaderWidget:
 * a Widget subclass with a $view, data supplied via public methods the Blade
 * partial calls directly.
 */
class HealthCheckStats extends Widget
{
    use HasMonitoringVisuals;
    use InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.system.health-check-rows';

    /**
     * key => [label, sub-label, icon]
     */
    private const CHECKS = [
        'database'  => ['Database', 'MySQL connectivity', 'heroicon-o-circle-stack'],
        'cache'     => ['Cache', 'Cache store read/write', 'heroicon-o-bolt'],
        'queue'     => ['Queue', 'Worker connection', 'heroicon-o-queue-list'],
        'storage'   => ['Storage', 'Writable storage path', 'heroicon-o-folder'],
        'scheduler' => ['Scheduler', 'Cron heartbeat', 'heroicon-o-clock'],
        'assets'    => ['Assets', 'Compiled build manifest', 'heroicon-o-cube'],
        'backup'    => ['Backup', 'Last successful backup age', 'heroicon-o-archive-box'],
    ];

    /**
     * One presentation row per check, for the Blade partial to render.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        $result = $this->cachedHealthData('rows', fn () => app(HealthCheckService::class)->snapshot());
        $checks = $result['checks'] ?? [];

        $rows = [];

        foreach (self::CHECKS as $key => [$label, $sub, $icon]) {
            $check = $checks[$key] ?? ['status' => 'unknown', 'detail' => '—', 'response_time_ms' => null];

            $recent = HealthCheckSnapshot::forCheck($key)
                ->orderByDesc('checked_at')
                ->limit(12)
                ->get()
                ->reverse()
                ->values();

            $rows[] = [
                'key'         => $key,
                'label'       => $label,
                'sub'         => $sub,
                'icon'        => $icon,
                'status'      => $check['status'],
                'pill'        => $this->pillLabel($check['status']),
                'state'       => $this->colorToState($this->statusColor($check['status'])),
                'detail'      => $check['detail'] ?? '',
                'latency'     => $check['response_time_ms'] !== null ? $check['response_time_ms'] . 'ms' : null,
                'sparkline'   => $recent->map(fn ($row) => $this->colorToState($this->statusColor($row->status)))->all(),
                'lastChecked' => $recent->last()?->checked_at?->diffForHumans() ?? 'never',
                'url'         => $this->urlFor($key),
            ];
        }

        return $rows;
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'ok'                       => 'success',
            'stale', 'missing', 'none' => 'warning',
            'fail'                     => 'danger',
            default                    => 'gray',
        };
    }

    private function pillLabel(string $status): string
    {
        return match ($status) {
            'ok'      => 'Operational',
            'fail'    => 'Failing',
            'stale'   => 'Stale',
            'missing' => 'Missing',
            'none'    => 'None',
            default   => 'Unknown',
        };
    }

    private function urlFor(string $key): ?string
    {
        return match ($key) {
            'database', 'storage', 'assets' => ServerMonitor::getUrl(),
            'cache'                          => CacheDashboard::getUrl(),
            'queue'                          => QueueMonitor::getUrl(),
            'scheduler'                      => ScheduledTasksPage::getUrl(),
            'backup'                         => BackupDashboard::getUrl(),
            default                          => null,
        };
    }
}
