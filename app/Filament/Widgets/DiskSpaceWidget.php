<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DiskSpaceWidget extends BaseWidget
{
    public function getDescription(): ?string
    {
        return 'Server disk usage statistics';
    }

    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '120s';

    // Full-width so the 3 stats lay out horizontally (System Health strip)
    // instead of stacking vertically in a half-width column.
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -15;

    protected ?string $heading = 'Disk Usage';

    protected function getStats(): array
    {
        try {
            $space = $this->cachedHealthData('disk', function (): array {
                $path = storage_path();

                return [
                    'total' => disk_total_space($path),
                    'free' => disk_free_space($path),
                ];
            });

            $total = $space['total'];
            $free = $space['free'];
            $used = $total - $free;
            $usedPercent = round(($used / $total) * 100, 1);

            $totalGB = round($total / 1024 / 1024 / 1024, 2);
            $usedGB = round($used / 1024 / 1024 / 1024, 2);
            $freeGB = round($free / 1024 / 1024 / 1024, 2);

            $color = match (true) {
                $usedPercent > 90 => 'danger',
                $usedPercent > 75 => 'warning',
                default => 'success',
            };

            $level = $usedPercent > 90 ? 'danger' : ($usedPercent > 70 ? 'warning' : 'success');

            $barColor = match (true) {
                $usedPercent > 90 => '#ef4444',
                $usedPercent > 75 => '#f59e0b',
                default => '#22c55e',
            };

            // Three stats so this widget matches the height of the adjacent
            // CacheStatusWidget (also 3 stats) — a single stat left a large
            // empty gap below it in the half-width System Health column.
            return [
                Stat::make('Disk Usage', "{$usedPercent}%")
                    ->description("{$freeGB} GB free of {$totalGB} GB" . ($usedPercent > 90 ? ' — Critical' : ''))
                    ->descriptionIcon('heroicon-o-server-stack')
                    ->color($level)
                    ->extraAttributes([
                        // Progress bar always; a red/amber alert edge only when
                        // disk usage crosses the warning/critical thresholds.
                        'class' => 'op-stat-bar' . ($usedPercent > 90 ? ' op-health-down' : ($usedPercent > 75 ? ' op-health-warn' : '')),
                        'style' => "--op-bar: {$usedPercent}%; --op-bar-color: {$barColor};",
                    ]),
                Stat::make('Free Space', "{$freeGB} GB")
                    ->description('Available storage')
                    ->descriptionIcon('heroicon-o-circle-stack')
                    ->color($usedPercent > 90 ? 'danger' : 'success'),
                Stat::make('Total Capacity', "{$totalGB} GB")
                    ->description('Storage volume')
                    ->descriptionIcon('heroicon-o-server')
                    ->color('gray'),
            ];
        } catch (\Exception $e) {
            report($e);
            return [
                Stat::make('Disk Usage', 'N/A')
                    ->description('Unable to read disk space')
                    ->descriptionIcon('heroicon-o-server-stack')
                    ->color('danger'),
            ];
        }
    }
}
