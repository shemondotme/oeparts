<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class QueueWorkerStatusWidget extends BaseWidget
{
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -27;

    protected ?string $heading = 'Queue Worker Status';

    public function getDescription(): ?string
    {
        return 'Queue worker health and throughput metrics';
    }

    protected function getStats(): array
    {
        try {
            $d = $this->cachedHealthData('queue_worker', function (): array {
                $pending = DB::table('jobs')->count();
                $failed = DB::table('failed_jobs')
                    ->where('failed_at', '>=', now()->subHour())
                    ->count();
                $failedTotal = DB::table('failed_jobs')->count();

                $recentJob = DB::table('jobs')
                    ->whereNotNull('reserved_at')
                    ->orderByDesc('reserved_at')
                    ->value('reserved_at');

                $isRunning = $recentJob !== null && now()->diffInSeconds($recentJob) < 300;

                $processed = DB::table('jobs')
                    ->where('reserved_at', '>=', now()->subHour())
                    ->orWhere(function ($q) {
                        $q->whereNull('reserved_at')
                          ->where('created_at', '>=', now()->subHour());
                    })
                    ->count();

                return [
                    'isRunning' => $isRunning,
                    'pending' => $pending,
                    'failed1h' => $failed,
                    'failedTotal' => $failedTotal,
                    'processed1h' => $processed,
                    'recentJobSeconds' => $recentJob ? now()->diffInSeconds($recentJob) : null,
                ];
            });

            $statusLabel = $d['isRunning'] ? 'Running' : ($d['pending'] > 0 ? 'Stopped' : 'Idle');
            $statusColor = $d['isRunning'] ? 'success' : ($d['pending'] > 0 ? 'danger' : 'gray');
            $statusIcon = $d['isRunning'] ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';

            return [
                Stat::make('Status', $statusLabel)
                    ->description($d['recentJobSeconds'] !== null ? "Last job {$d['recentJobSeconds']}s ago" : 'No recent activity')
                    ->descriptionIcon($statusIcon)
                    ->color($statusColor),
                Stat::make('Pending', number_format($d['pending']))
                    ->description('Jobs in queue')
                    ->descriptionIcon('heroicon-o-clock')
                    ->color($d['pending'] > 0 ? 'warning' : 'success'),
                Stat::make('Processed (1h)', number_format($d['processed1h']))
                    ->description('Last hour throughput')
                    ->descriptionIcon('heroicon-o-arrow-trending-up')
                    ->color('info'),
                Stat::make('Failed (1h)', number_format($d['failed1h']))
                    ->description($d['failedTotal'] > 0 ? "{$d['failedTotal']} total failed" : 'No failures')
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color($d['failed1h'] > 0 ? 'danger' : 'success'),
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                Stat::make('Queue', 'Unavailable')
                    ->description('Cannot read queue status')
                    ->descriptionIcon('heroicon-o-x-circle')
                    ->color('danger'),
            ];
        }
    }
}
