<?php

namespace App\Filament\Widgets\System;

use App\Services\HealthCheckService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HealthCheckStats extends BaseWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * key => [label, description, icon]
     */
    private const CHECKS = [
        'database'  => ['Database', 'MySQL connectivity', 'heroicon-o-circle-stack'],
        'cache'     => ['Cache', 'Cache store read/write', 'heroicon-o-bolt'],
        'queue'     => ['Queue', 'Worker connection', 'heroicon-o-queue-list'],
        'storage'   => ['Storage', 'Writable storage path', 'heroicon-o-folder'],
        'scheduler' => ['Scheduler', 'Cron heartbeat', 'heroicon-o-clock'],
        'assets'    => ['Assets', 'Compiled build manifest', 'heroicon-o-cube'],
    ];

    protected function getStats(): array
    {
        $results = app(HealthCheckService::class)->runAll();
        $checks = $results['checks'] ?? [];

        $overall = match ($results['status'] ?? 'fail') {
            'ok'       => ['All Systems Operational', 'success', 'heroicon-m-check-badge'],
            'degraded' => ['Degraded — action needed', 'warning', 'heroicon-m-exclamation-triangle'],
            default    => ['Systems Down', 'danger', 'heroicon-m-x-circle'],
        };

        $stats = [
            Stat::make('Overall Status', $overall[0])
                ->description('Version ' . ($results['version'] ?? '—'))
                ->descriptionIcon($overall[2])
                ->color($overall[1]),
        ];

        foreach (self::CHECKS as $key => [$label, $description, $icon]) {
            [$value, $color] = $this->presentStatus($checks[$key] ?? 'unknown');

            $stats[] = Stat::make($label, $value)
                ->description($description)
                ->descriptionIcon($icon)
                ->color($color);
        }

        return $stats;
    }

    /**
     * @return array{0: string, 1: string} [value label, color]
     */
    private function presentStatus(string $status): array
    {
        return match ($status) {
            'ok'      => ['Operational', 'success'],
            'fail'    => ['Failing', 'danger'],
            'missing' => ['Missing', 'warning'],
            'stale'   => ['Stale', 'warning'],
            default   => ['Unknown', 'gray'],
        };
    }
}
