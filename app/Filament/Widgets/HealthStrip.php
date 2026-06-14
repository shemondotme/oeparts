<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthStrip extends BaseWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Real-time system health indicators';
    }

    protected static bool $isLazy = false;

    protected static ?int $sort = -13;

    protected ?string $heading = 'System Health';

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // SHORT_TTL (15s) keeps the strip live while halving the cost of the
        // 30s polling — concurrent dashboards share one check run.
        $checks = $this->cachedHealthData('checks', fn (): array => [
            'db' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'scheduler' => $this->checkScheduler(),
            'cache' => $this->checkCache(),
        ]);

        $dbStatus = $checks['db'];
        $redisStatus = $checks['redis'];
        $queueStatus = $checks['queue'];
        $storageStatus = $checks['storage'];
        $schedulerStatus = $checks['scheduler'];
        $cacheStatus = $checks['cache'];

        return [
            Stat::make('Database', $dbStatus['label'])
                ->description($dbStatus['detail'])
                ->descriptionColor($dbStatus['color'])
                ->color($dbStatus['color'])
                ->icon($dbStatus['icon'])
                ->extraAttributes(['data-stat-status' => $dbStatus['color']]),
            Stat::make('Redis', $redisStatus['label'])
                ->description($redisStatus['detail'])
                ->descriptionColor($redisStatus['color'])
                ->color($redisStatus['color'])
                ->icon($redisStatus['icon'])
                ->extraAttributes(['data-stat-status' => $redisStatus['color']]),
            Stat::make('Queue', $queueStatus['label'])
                ->description($queueStatus['detail'])
                ->descriptionColor($queueStatus['color'])
                ->color($queueStatus['color'])
                ->icon($queueStatus['icon'])
                ->extraAttributes(['data-stat-status' => $queueStatus['color']]),
            Stat::make('Storage', $storageStatus['label'])
                ->description($storageStatus['detail'])
                ->descriptionColor($storageStatus['color'])
                ->color($storageStatus['color'])
                ->icon($storageStatus['icon'])
                ->extraAttributes(['data-stat-status' => $storageStatus['color']]),
            Stat::make('Scheduler', $schedulerStatus['label'])
                ->description($schedulerStatus['detail'])
                ->descriptionColor($schedulerStatus['color'])
                ->color($schedulerStatus['color'])
                ->icon($schedulerStatus['icon'])
                ->extraAttributes(['data-stat-status' => $schedulerStatus['color']]),
            Stat::make('Cache', $cacheStatus['label'])
                ->description($cacheStatus['detail'])
                ->descriptionColor($cacheStatus['color'])
                ->color($cacheStatus['color'])
                ->icon($cacheStatus['icon'])
                ->extraAttributes(['data-stat-status' => $cacheStatus['color']]),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $ms = round((microtime(true) - $start) * 1000);

            $tables = count(DB::select('SHOW TABLES'));

            return [
                'label' => 'Connected',
                'detail' => "{$tables} tables · {$ms}ms",
                'color' => 'success',
                'icon' => 'heroicon-o-check-circle',
            ];
        } catch (\Exception $e) {
            return [
                'label' => 'Error',
                'detail' => $e->getMessage(),
                'color' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ];
        }
    }

    private function checkRedis(): array
    {
        if (!extension_loaded('redis') && !class_exists('Redis')) {
            return [
                'label' => 'Not installed',
                'detail' => 'PHP Redis extension not available',
                'color' => 'gray',
                'icon' => 'heroicon-o-minus-circle',
            ];
        }

        try {
            $start = microtime(true);
            Cache::store('redis')->put('_health_check', '1', 10);
            Cache::store('redis')->get('_health_check');
            $ms = round((microtime(true) - $start) * 1000);

            return [
                'label' => 'Responding',
                'detail' => "{$ms}ms",
                'color' => 'success',
                'icon' => 'heroicon-o-check-circle',
            ];
        } catch (\Throwable $e) {
            return [
                'label' => config('cache.default') === 'redis' ? 'Unavailable' : 'Not configured',
                'detail' => config('cache.default') === 'redis' ? 'Connection failed' : 'Using ' . config('cache.default'),
                'color' => config('cache.default') === 'redis' ? 'danger' : 'gray',
                'icon' => config('cache.default') === 'redis' ? 'heroicon-o-x-circle' : 'heroicon-o-minus-circle',
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $lastJob = DB::table('jobs')
                ->orderByDesc('reserved_at')
                ->value('reserved_at');

            if ($lastJob) {
                $secondsAgo = now()->diffInSeconds($lastJob);

                return [
                    'label' => 'Active',
                    'detail' => "Last job {$secondsAgo}s ago",
                    'color' => 'success',
                    'icon' => 'heroicon-o-check-circle',
                ];
            }

            return [
                'label' => 'Idle',
                'detail' => 'No recent jobs',
                'color' => 'gray',
                'icon' => 'heroicon-o-minus-circle',
            ];
        } catch (\Exception $e) {
            return [
                'label' => 'Error',
                'detail' => 'Queue table not accessible',
                'color' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk('public');
            $path = $disk->path('');

            if (!is_dir($path)) {
                return [
                    'label' => 'Not mounted',
                    'detail' => 'Storage path not found',
                    'color' => 'warning',
                    'icon' => 'heroicon-o-exclamation-triangle',
                ];
            }

            $total = disk_total_space($path);
            $free = disk_free_space($path);
            $usedPct = $total > 0 ? round((($total - $free) / $total) * 100) : 0;

            return [
                'label' => "{$usedPct}% used",
                'detail' => number_format($free / 1024 / 1024 / 1024, 1) . ' GB free',
                'color' => $usedPct > 90 ? 'danger' : ($usedPct > 75 ? 'warning' : 'success'),
                'icon' => $usedPct > 90 ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle',
            ];
        } catch (\Exception $e) {
            return [
                'label' => 'Unknown',
                'detail' => 'Cannot check storage',
                'color' => 'gray',
                'icon' => 'heroicon-o-minus-circle',
            ];
        }
    }

    private function checkScheduler(): array
    {
        try {
            $heartbeat = Cache::get('scheduler_heartbeat');

            if ($heartbeat) {
                $secondsAgo = now()->diffInSeconds($heartbeat);

                if ($secondsAgo < 120) {
                    return [
                        'label' => 'Running',
                        'detail' => "Last heartbeat {$secondsAgo}s ago",
                        'color' => 'success',
                        'icon' => 'heroicon-o-check-circle',
                    ];
                }

                return [
                    'label' => 'Stale',
                    'detail' => "{$secondsAgo}s since heartbeat",
                    'color' => 'danger',
                    'icon' => 'heroicon-o-x-circle',
                ];
            }

            return [
                'label' => 'No heartbeat',
                'detail' => 'Scheduler may not be running',
                'color' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ];
        } catch (\Exception $e) {
            return [
                'label' => 'Error',
                'detail' => 'Cannot check scheduler',
                'color' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $driver = config('cache.default');

            return [
                'label' => ucfirst($driver),
                'detail' => 'Driver: ' . $driver,
                'color' => in_array($driver, ['redis', 'file', 'database']) ? 'success' : 'warning',
                'icon' => in_array($driver, ['redis', 'file', 'database']) ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle',
            ];
        } catch (\Exception $e) {
            return [
                'label' => 'Error',
                'detail' => 'Cannot check cache',
                'color' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ];
        }
    }
}
