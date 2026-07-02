<?php

namespace App\Filament\Widgets\System;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class QueueStats extends BaseWidget
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

        $pendingDesc = $d['by_queue'] !== []
            ? collect($d['by_queue'])->map(fn ($c, $q) => "{$q}: {$c}")->implode(' · ')
            : 'All queues clear';

        return [
            Stat::make('Pending', number_format($d['total_pending']))
                ->description($pendingDesc)
                ->descriptionIcon('heroicon-m-queue-list')
                ->color($d['total_pending'] > 0 ? 'warning' : 'success'),

            Stat::make('Processing', number_format($d['processing']))
                ->description('Reserved in last 5 min')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),

            Stat::make('Completed (1h)', number_format($d['completed_hour']))
                ->description('Finished in last hour')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Failed (24h)', number_format($d['failed_24h']))
                ->description('See Failed Jobs to retry')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($d['failed_24h'] > 0 ? 'danger' : 'gray'),
        ];
    }

    private function readStats(): array
    {
        $jobsTable = config('queue.connections.database.table', 'jobs');

        $byQueue = [];
        try {
            $byQueue = DB::table($jobsTable)
                ->select('queue', DB::raw('COUNT(*) as count'))
                ->groupBy('queue')
                ->pluck('count', 'queue')
                ->toArray();
        } catch (\Throwable $e) {
        }

        $processing = 0;
        try {
            $processing = DB::table($jobsTable)
                ->where('reserved_at', '>', now()->subMinutes(5))
                ->count();
        } catch (\Throwable $e) {
        }

        $failed24h = 0;
        try {
            $failed24h = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();
        } catch (\Throwable $e) {
        }

        $completedHour = 0;
        try {
            $completedHour = (int) DB::table('job_batches')
                ->where('finished_at', '>=', now()->subHour())
                ->sum('total_jobs');
        } catch (\Throwable $e) {
        }

        return [
            'total_pending' => array_sum($byQueue),
            'by_queue' => $byQueue,
            'processing' => $processing,
            'failed_24h' => $failed24h,
            'completed_hour' => $completedHour,
        ];
    }
}
