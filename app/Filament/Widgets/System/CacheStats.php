<?php

namespace App\Filament\Widgets\System;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Redis;

class CacheStats extends BaseWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $d = $this->readStats();

        if (! $d['available']) {
            return [
                Stat::make('Cache Store', 'Unavailable')
                    ->description('Redis connection failed')
                    ->descriptionIcon('heroicon-m-x-circle')
                    ->color('danger'),
            ];
        }

        $hitColor = $d['hit_rate'] >= 80 ? 'success' : ($d['hit_rate'] >= 50 ? 'warning' : 'danger');

        return [
            Stat::make('Hit Rate', $d['hit_rate'] . '%')
                ->description(number_format($d['hits']) . ' hits · ' . number_format($d['misses']) . ' misses')
                ->descriptionIcon('heroicon-m-bolt')
                ->color($hitColor),

            Stat::make('Memory Used', $d['memory_used'])
                ->description('Peak ' . $d['memory_peak'])
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('primary'),

            Stat::make('Total Keys', number_format($d['total_keys']))
                ->description($d['connected_clients'] . ' connected clients')
                ->descriptionIcon('heroicon-m-key')
                ->color('gray'),

            Stat::make('Uptime', $d['uptime'])
                ->description('Since last restart')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }

    private function readStats(): array
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info();

            $hits = (int) ($info['keyspace_hits'] ?? 0);
            $misses = (int) ($info['keyspace_misses'] ?? 0);
            $total = $hits + $misses;

            return [
                'available' => true,
                'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 1) : 0,
                'hits' => $hits,
                'misses' => $misses,
                'memory_used' => $info['used_memory_human'] ?? 'N/A',
                'memory_peak' => $info['used_memory_peak_human'] ?? 'N/A',
                'total_keys' => (int) $redis->dbSize(),
                'connected_clients' => (int) ($info['connected_clients'] ?? 0),
                'uptime' => $this->formatUptime((int) ($info['uptime_in_seconds'] ?? 0)),
            ];
        } catch (\Throwable $e) {
            return ['available' => false];
        }
    }

    private function formatUptime(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return "{$days}d {$hours}h";
        }

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }
}
