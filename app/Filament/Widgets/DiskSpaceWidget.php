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

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

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

            return [
                Stat::make('Disk Usage', "{$usedPercent}%")
                    ->description("{$usedGB} / {$totalGB} GB · {$freeGB} GB free" . ($usedPercent > 90 ? ' — Critical' : ''))
                    ->descriptionIcon('heroicon-o-server-stack')
                    ->color($level),
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
