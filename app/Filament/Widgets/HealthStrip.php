<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\System\CacheDashboard;
use App\Filament\Pages\System\QueueMonitor;
use App\Filament\Pages\System\ScheduledTasksPage;
use App\Filament\Pages\System\ServerMonitor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthStrip extends StatsOverviewWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
    use \App\Filament\Widgets\Concerns\HasMonitoringVisuals;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = -13;

    protected function getStats(): array
    {
        $checks = $this->cachedHealthData('checks', fn (): array => [
            $this->checkDatabase(),
            $this->checkRedis(),
            $this->checkQueue(),
            $this->checkStorage(),
            $this->checkScheduler(),
            $this->checkCache(),
        ]);

        return array_map(function (array $check): Stat {
            // Live pulse dot for healthy checks + a status-colored accent on
            // the card's left edge (clean at-a-glance status, no cramped
            // inline strip).
            $live = $check['color'] === 'success';

            $stat = Stat::make($check['label'], $check['value'])
                ->description($this->statusDescription($check['description'], live: $live))
                ->color($check['color'])
                ->extraAttributes(['class' => 'op-health-' . $this->colorToState($check['color'])]);

            // Healthy checks show the pulse dot instead of a redundant icon;
            // degraded checks keep their warning/error icon.
            if (! $live) {
                $stat->descriptionIcon($check['icon']);
            }

            $url = match ($check['label']) {
                'Database', 'Redis', 'Storage' => ServerMonitor::getUrl(),
                'Queue' => QueueMonitor::getUrl(),
                'Scheduler' => ScheduledTasksPage::getUrl(),
                'Cache' => CacheDashboard::getUrl(),
                default => null,
            };

            if ($url !== null) {
                $stat->url($url);
            }

            return $stat;
        }, $checks);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $ms = round((microtime(true) - $start) * 1000);
            $tables = count(DB::select('SHOW TABLES'));

            return [
                'label' => 'Database',
                'value' => "{$tables} tables",
                'description' => "{$ms}ms · connected",
                'icon' => 'heroicon-m-check-circle',
                'color' => 'success',
            ];
        } catch (\Exception $e) {
            return [
                'label' => 'Database', 'value' => 'Offline',
                'description' => 'Connection failed', 'icon' => 'heroicon-m-x-circle', 'color' => 'danger',
            ];
        }
    }

    private function checkRedis(): array
    {
        if (! extension_loaded('redis') && ! class_exists('Redis')) {
            return [
                'label' => 'Redis', 'value' => 'Not installed',
                'description' => 'Optional in local dev', 'icon' => 'heroicon-m-minus-circle', 'color' => 'gray',
            ];
        }

        try {
            $start = microtime(true);
            Cache::store('redis')->put('_health_check', '1', 10);
            Cache::store('redis')->get('_health_check');
            $ms = round((microtime(true) - $start) * 1000);

            return [
                'label' => 'Redis', 'value' => "{$ms}ms",
                'description' => 'Connected', 'icon' => 'heroicon-m-bolt', 'color' => 'success',
            ];
        } catch (\Throwable) {
            $isRequired = config('cache.default') === 'redis';

            return [
                'label' => 'Redis',
                'value' => $isRequired ? 'Unavailable' : 'Not configured',
                'description' => $isRequired ? 'Required in production' : 'Using fallback driver',
                'icon' => $isRequired ? 'heroicon-m-x-circle' : 'heroicon-m-minus-circle',
                'color' => $isRequired ? 'danger' : 'gray',
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $lastJob = DB::table('jobs')->orderByDesc('reserved_at')->value('reserved_at');

            if ($lastJob) {
                $s = now()->diffInSeconds($lastJob);

                return [
                    'label' => 'Queue', 'value' => 'Active',
                    'description' => "Last job {$s}s ago", 'icon' => 'heroicon-m-arrow-path', 'color' => 'success',
                ];
            }

            return [
                'label' => 'Queue', 'value' => 'Idle',
                'description' => 'No pending jobs', 'icon' => 'heroicon-m-check-circle', 'color' => 'success',
            ];
        } catch (\Exception) {
            return [
                'label' => 'Queue', 'value' => 'Error',
                'description' => 'Cannot read queue', 'icon' => 'heroicon-m-x-circle', 'color' => 'danger',
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = Storage::disk('public')->path('');

            if (! is_dir($path)) {
                return [
                    'label' => 'Storage', 'value' => 'Not mounted',
                    'description' => 'Check disk mount', 'icon' => 'heroicon-m-exclamation-triangle', 'color' => 'warning',
                ];
            }

            $total = disk_total_space($path);
            $free = disk_free_space($path);
            $usedPct = $total > 0 ? round((($total - $free) / $total) * 100) : 0;
            $freeGb = number_format($free / 1024 / 1024 / 1024, 1);

            return [
                'label' => 'Storage',
                'value' => "{$usedPct}% used",
                'description' => "{$freeGb} GB free" . ($usedPct > 90 ? ' — critical' : ''),
                'icon' => $usedPct > 90 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-server-stack',
                'color' => $usedPct > 90 ? 'danger' : ($usedPct > 75 ? 'warning' : 'success'),
            ];
        } catch (\Exception) {
            return [
                'label' => 'Storage', 'value' => 'Unknown',
                'description' => 'Cannot read disk', 'icon' => 'heroicon-m-minus-circle', 'color' => 'gray',
            ];
        }
    }

    private function checkScheduler(): array
    {
        try {
            $heartbeat = Cache::get('scheduler_heartbeat');

            if ($heartbeat) {
                $s = now()->diffInSeconds($heartbeat);

                return $s < 120
                    ? ['label' => 'Scheduler', 'value' => 'Running', 'description' => "Beat {$s}s ago", 'icon' => 'heroicon-m-check-circle', 'color' => 'success']
                    : ['label' => 'Scheduler', 'value' => 'Stale', 'description' => "{$s}s since last beat", 'icon' => 'heroicon-m-exclamation-triangle', 'color' => 'danger'];
            }

            return [
                'label' => 'Scheduler', 'value' => 'No heartbeat',
                'description' => 'Cron may not be running', 'icon' => 'heroicon-m-exclamation-triangle', 'color' => 'warning',
            ];
        } catch (\Exception) {
            return [
                'label' => 'Scheduler', 'value' => 'Error',
                'description' => 'Cannot read status', 'icon' => 'heroicon-m-x-circle', 'color' => 'danger',
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $driver = config('cache.default');
            $ok = in_array($driver, ['redis', 'file', 'database'], true);

            // Production requires Redis (file/database drivers are a
            // dev-only convenience) — surface that instead of a green tile.
            if ($ok && $driver !== 'redis' && app()->isProduction()) {
                return [
                    'label' => 'Cache',
                    'value' => ucfirst($driver),
                    'description' => 'Redis required in production',
                    'icon' => 'heroicon-m-exclamation-triangle',
                    'color' => 'warning',
                ];
            }

            return [
                'label' => 'Cache',
                'value' => ucfirst($driver),
                'description' => $ok ? 'Driver active' : 'Unknown driver',
                'icon' => $ok ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle',
                'color' => $ok ? 'success' : 'warning',
            ];
        } catch (\Exception) {
            return [
                'label' => 'Cache', 'value' => 'Error',
                'description' => 'Cannot read config', 'icon' => 'heroicon-m-x-circle', 'color' => 'danger',
            ];
        }
    }
}
