<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthStrip extends Widget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Real-time system health indicators';
    }

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.system-health-strip';

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = -13;

    protected function getViewData(): array
    {
        return [
            'checks' => $this->cachedHealthData('checks', fn (): array => [
                'db'        => $this->checkDatabase(),
                'redis'     => $this->checkRedis(),
                'queue'     => $this->checkQueue(),
                'storage'   => $this->checkStorage(),
                'scheduler' => $this->checkScheduler(),
                'cache'     => $this->checkCache(),
            ]),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $start  = microtime(true);
            DB::connection()->getPdo();
            $ms     = round((microtime(true) - $start) * 1000);
            $tables = count(DB::select('SHOW TABLES'));

            return [
                'label'  => 'Connected',
                'detail' => "{$tables} tables · {$ms}ms",
                'color'  => 'success',
            ];
        } catch (\Exception $e) {
            return ['label' => 'Error', 'detail' => $e->getMessage(), 'color' => 'danger'];
        }
    }

    private function checkRedis(): array
    {
        if (! extension_loaded('redis') && ! class_exists('Redis')) {
            return ['label' => 'Not installed', 'detail' => 'PHP Redis extension not available', 'color' => 'gray'];
        }

        try {
            $start = microtime(true);
            Cache::store('redis')->put('_health_check', '1', 10);
            Cache::store('redis')->get('_health_check');
            $ms = round((microtime(true) - $start) * 1000);

            return ['label' => 'Responding', 'detail' => "{$ms}ms", 'color' => 'success'];
        } catch (\Throwable) {
            $isRequired = config('cache.default') === 'redis';

            return [
                'label'  => $isRequired ? 'Unavailable' : 'Not configured',
                'detail' => $isRequired ? 'Connection failed' : 'Using ' . config('cache.default'),
                'color'  => $isRequired ? 'danger' : 'gray',
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $lastJob = DB::table('jobs')->orderByDesc('reserved_at')->value('reserved_at');

            if ($lastJob) {
                $s = now()->diffInSeconds($lastJob);

                return ['label' => 'Active', 'detail' => "Last job {$s}s ago", 'color' => 'success'];
            }

            return ['label' => 'Idle', 'detail' => 'No recent jobs', 'color' => 'gray'];
        } catch (\Exception) {
            return ['label' => 'Error', 'detail' => 'Queue table not accessible', 'color' => 'danger'];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = Storage::disk('public')->path('');

            if (! is_dir($path)) {
                return ['label' => 'Not mounted', 'detail' => 'Storage path not found', 'color' => 'warning'];
            }

            $total   = disk_total_space($path);
            $free    = disk_free_space($path);
            $usedPct = $total > 0 ? round((($total - $free) / $total) * 100) : 0;

            return [
                'label'  => "{$usedPct}% used",
                'detail' => number_format($free / 1024 / 1024 / 1024, 1) . ' GB free',
                'color'  => $usedPct > 90 ? 'danger' : ($usedPct > 75 ? 'warning' : 'success'),
            ];
        } catch (\Exception) {
            return ['label' => 'Unknown', 'detail' => 'Cannot check storage', 'color' => 'gray'];
        }
    }

    private function checkScheduler(): array
    {
        try {
            $heartbeat = Cache::get('scheduler_heartbeat');

            if ($heartbeat) {
                $s = now()->diffInSeconds($heartbeat);

                return $s < 120
                    ? ['label' => 'Running',      'detail' => "Heartbeat {$s}s ago",      'color' => 'success']
                    : ['label' => 'Stale',        'detail' => "{$s}s since heartbeat",     'color' => 'danger'];
            }

            return ['label' => 'No heartbeat', 'detail' => 'Scheduler may not be running', 'color' => 'warning'];
        } catch (\Exception) {
            return ['label' => 'Error', 'detail' => 'Cannot check scheduler', 'color' => 'danger'];
        }
    }

    private function checkCache(): array
    {
        try {
            $driver = config('cache.default');

            return [
                'label'  => ucfirst($driver),
                'detail' => 'Driver: ' . $driver,
                'color'  => in_array($driver, ['redis', 'file', 'database']) ? 'success' : 'warning',
            ];
        } catch (\Exception) {
            return ['label' => 'Error', 'detail' => 'Cannot check cache', 'color' => 'danger'];
        }
    }
}
