<?php

namespace App\Filament\Widgets\System;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServerStats extends BaseWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $mem = $this->memory();
        $disk = $this->disk();
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;

        $loadValue = $load ? round($load[0] ?? 0, 2) : '—';
        $loadDesc = $load
            ? '5m ' . round($load[1] ?? 0, 2) . ' · 15m ' . round($load[2] ?? 0, 2)
            : 'Not available on this host';

        return [
            Stat::make('CPU Load (1m)', (string) $loadValue)
                ->description($loadDesc)
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('gray'),

            Stat::make('Memory', $mem['usage_mb'] . ' / ' . $mem['limit_mb'] . ' MB')
                ->description('Peak ' . $mem['peak_mb'] . ' MB · ' . $mem['usage_percent'] . '% of limit')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($mem['usage_percent'] >= 90 ? 'danger' : ($mem['usage_percent'] >= 70 ? 'warning' : 'success')),

            Stat::make('Disk', $disk['used_gb'] . ' / ' . $disk['total_gb'] . ' GB')
                ->description($disk['free_gb'] . ' GB free · ' . $disk['usage_percent'] . '% used')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color($disk['usage_percent'] >= 90 ? 'danger' : ($disk['usage_percent'] >= 75 ? 'warning' : 'success')),

            Stat::make('PHP', PHP_VERSION)
                ->description(php_sapi_name() . ' · memory_limit ' . ini_get('memory_limit'))
                ->descriptionIcon('heroicon-m-code-bracket')
                ->color('primary'),

            Stat::make('Laravel', app()->version())
                ->description('Env: ' . config('app.env') . ' · Debug: ' . (config('app.debug') ? 'on' : 'off'))
                ->descriptionIcon('heroicon-m-bolt')
                ->color(config('app.env') === 'production' ? 'success' : 'warning'),

            Stat::make('Drivers', ucfirst((string) config('cache.default')))
                ->description('Queue: ' . config('queue.default') . ' · Session: ' . config('session.driver'))
                ->descriptionIcon('heroicon-m-server-stack')
                ->color('gray'),
        ];
    }

    private function memory(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = (int) ini_get('memory_limit');
        $limit = $limit > 0 ? $limit * 1024 * 1024 : 256 * 1024 * 1024;

        return [
            'usage_mb' => round($usage / 1048576, 1),
            'peak_mb' => round($peak / 1048576, 1),
            'limit_mb' => round($limit / 1048576, 1),
            'usage_percent' => $limit > 0 ? round(($usage / $limit) * 100, 1) : 0,
        ];
    }

    private function disk(): array
    {
        $path = storage_path();
        $total = disk_total_space($path) ?: 1;
        $free = disk_free_space($path) ?: 0;
        $used = $total - $free;

        return [
            'total_gb' => round($total / 1073741824, 2),
            'used_gb' => round($used / 1073741824, 2),
            'free_gb' => round($free / 1073741824, 2),
            'usage_percent' => round(($used / $total) * 100, 1),
        ];
    }
}
