<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheDashboard extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Cache Dashboard';

    protected string $view = 'filament.pages.system.cache-dashboard';

    protected static ?string $pollingInterval = '30s';

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-server-stack';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasRole('super_admin');
    }

    public function getCacheStats(): array
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info();

            return [
                'hit_rate' => $this->calculateHitRate($info),
                'memory_used' => $info['used_memory_human'] ?? 'N/A',
                'memory_peak' => $info['used_memory_peak_human'] ?? 'N/A',
                'total_keys' => $redis->dbSize(),
                'uptime' => $this->formatUptime($info['uptime_in_seconds'] ?? 0),
                'connected_clients' => $info['connected_clients'] ?? 0,
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'hit_rate' => 'N/A',
                'memory_used' => 'N/A',
                'memory_peak' => 'N/A',
                'total_keys' => 0,
                'uptime' => 'N/A',
                'connected_clients' => 0,
                'hits' => 0,
                'misses' => 0,
            ];
        }
    }

    private function calculateHitRate(array $info): string
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return '0%';
        }

        return round(($hits / $total) * 100, 1) . '%';
    }

    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($days > 0) {
            return "{$days}d {$hours}h";
        }

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    public function clearApplicationCache(): void
    {
        try {
            $keys = Cache::getStore()->getRedis()->keys('laravel_database_*');
        } catch (\Exception $e) {
            Notification::make()
                ->title('Redis not available')
                ->body('Cannot clear cache: Redis driver is not active.')
                ->danger()
                ->send();
            return;
        }

        if (empty($keys)) {
            Notification::make()
                ->title('No application cache keys found')
                ->info()
                ->send();

            return;
        }

        $count = 0;
        foreach ($keys as $key) {
            $shortKey = str_replace('laravel_database_', '', $key);
            Cache::forget($shortKey);
            $count++;
        }

        Notification::make()
            ->title("Cleared {$count} cache keys")
            ->success()
            ->send();
    }

    public function getTopCachedKeys(): array
    {
        try {
            $redis = Redis::connection();
            $cursor = null;
            $keys = [];
            do {
                [$cursor, $result] = $redis->scan($cursor ?? 0, ['match' => 'laravel_*', 'count' => 50]);
                $keys = array_merge($keys, $result ?? []);
            } while ($cursor);

            $result = [];
            foreach (array_slice($keys, 0, 50) as $key) {
                $ttl = $redis->ttl($key);
                $result[] = [
                    'key' => $key,
                    'ttl' => $ttl > 0 ? $this->formatTtl($ttl) : 'No expiry',
                    'size' => strlen($redis->get($key) ?? ''),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function formatTtl(int $seconds): string
    {
        if ($seconds > 86400) {
            return round($seconds / 86400, 1) . 'd';
        }

        if ($seconds > 3600) {
            return round($seconds / 3600, 1) . 'h';
        }

        if ($seconds > 60) {
            return round($seconds / 60, 1) . 'm';
        }

        return $seconds . 's';
    }

    public function clearKey(string $key): void
    {
        Cache::forget($key);

        Notification::make()
            ->title('Cache key cleared')
            ->success()
            ->send();
    }
}
