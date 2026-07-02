<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\System\CacheDashboard;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class CacheStatusWidget extends BaseWidget
{
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;
    use Concerns\HasMonitoringVisuals;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    // Full-width so the 3 stats lay out horizontally (System Health strip).
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -28;

    protected ?string $heading = 'Cache Status';

    public function getDescription(): ?string
    {
        return 'Cache driver health and usage metrics';
    }

    protected function getStats(): array
    {
        try {
            $d = $this->cachedHealthData('cache_status', function (): array {
                $driver = config('cache.default');
                $store = Cache::store($driver);
                $keys = 0;

                try {
                    if ($driver === 'redis') {
                        $keys = (int) $store->connection()->dbsize();
                    } elseif ($driver === 'file') {
                        $path = config('cache.stores.file.path') ?? storage_path('framework/cache/data');
                        $keys = count(glob($path . '/*/*/*')) ?: 0;
                    }
                } catch (\Throwable $e) {
                    $keys = 0;
                }

                $hitRate = 0;
                try {
                    if ($driver === 'redis') {
                        $info = $store->connection()->info('stats');
                        $hits = (int) ($info['keyspace_hits'] ?? 0);
                        $misses = (int) ($info['keyspace_misses'] ?? 0);
                        $total = $hits + $misses;
                        $hitRate = $total > 0 ? round(($hits / $total) * 100, 1) : 0;
                    }
                } catch (\Throwable $e) {
                    $hitRate = 0;
                }

                return [
                    'driver' => $driver,
                    'keys' => $keys,
                    'hitRate' => $hitRate,
                    'configured' => in_array($driver, ['redis', 'file', 'database'], true),
                ];
            });

            $healthColor = $d['configured'] ? 'success' : 'warning';

            return [
                Stat::make('Driver', ucfirst($d['driver']))
                    ->description($this->statusDescription($d['configured'] ? 'Configured' : 'Fallback driver', live: $d['configured']))
                    ->descriptionIcon($d['configured'] ? null : 'heroicon-o-exclamation-triangle')
                    ->color($healthColor)
                    ->extraAttributes($this->alertAccent($d['configured'] ? null : 'warning'))
                    ->url(CacheDashboard::getUrl()),
                Stat::make('Cache Keys', number_format($d['keys']))
                    ->description('Total stored entries')
                    ->descriptionIcon('heroicon-o-circle-stack')
                    ->chart($this->rollingSamples('cache_keys', $d['keys']))
                    ->chartColor('info')
                    ->color('info'),
                Stat::make('Hit Rate', $d['hitRate'] > 0 ? "{$d['hitRate']}%" : '—')
                    ->description($d['hitRate'] > 80 ? 'Healthy' : ($d['hitRate'] > 0 ? 'Below optimal' : 'Tracking disabled (file cache)'))
                    ->descriptionIcon('heroicon-o-chart-bar-square')
                    ->chart($d['hitRate'] > 0 ? $this->rollingSamples('cache_hitrate', $d['hitRate']) : [0, 0])
                    ->chartColor($d['hitRate'] > 80 ? 'success' : ($d['hitRate'] > 0 ? 'warning' : 'gray'))
                    ->color($d['hitRate'] > 80 ? 'success' : ($d['hitRate'] > 0 ? 'warning' : 'gray')),
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                Stat::make('Cache', 'Unavailable')
                    ->description('Cannot read cache status')
                    ->descriptionIcon('heroicon-o-x-circle')
                    ->color('danger'),
            ];
        }
    }
}
