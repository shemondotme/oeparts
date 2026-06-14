<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RequestMetricsWidget extends BaseWidget
{
    public function getDescription(): ?string
    {
        return 'HTTP request performance metrics';
    }

    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -14;

    protected ?string $heading = 'Request Metrics';

    protected function getStats(): array
    {
        $metrics = $this->cachedWidgetData(function () {
            $jobsTable = config('queue.connections.database.table', 'jobs');
            $failedTable = 'failed_jobs';

            $pendingJobs = 0;
            try {
                $pendingJobs = DB::table($jobsTable)->count();
            } catch (\Exception $e) {}

            $failedJobs = 0;
            try {
                $failedJobs = DB::table($failedTable)
                    ->where('failed_at', '>=', now()->subHour())
                    ->count();
            } catch (\Exception $e) {}

            $emailsSent = 0;
            try {
                $emailsSent = DB::table('email_logs')
                    ->where('created_at', '>=', now()->subHour())
                    ->count();
            } catch (\Exception $e) {}

            $searches = 0;
            try {
                $searches = DB::table('search_logs')
                    ->where('created_at', '>=', now()->subHour())
                    ->count();
            } catch (\Exception $e) {}

            return [
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'emails_sent' => $emailsSent,
                'searches' => $searches,
            ];
        });

        $hasAnomaly = $metrics['failed_jobs'] > 5;
        $anomalyColor = $hasAnomaly ? 'danger' : '';

        return [
            Stat::make('Pending Jobs', number_format($metrics['pending_jobs']))
                ->description($metrics['pending_jobs'] > 0 ? 'In queue' : 'All clear')
                ->descriptionIcon('heroicon-o-clock')
                ->color($metrics['pending_jobs'] > 50 ? 'warning' : 'gray'),
            Stat::make('Failed (1h)', number_format($metrics['failed_jobs']))
                ->description($metrics['failed_jobs'] > 0 ? 'Needs attention' : 'No failures')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($metrics['failed_jobs'] > 0 ? 'danger' : 'success')
                ->extraAttributes($hasAnomaly ? ['class' => 'op-anomaly-bg'] : []),
            Stat::make('Emails Sent (1h)', number_format($metrics['emails_sent']))
                ->description('Last hour')
                ->descriptionIcon('heroicon-o-envelope')
                ->color('info'),
            Stat::make('Searches (1h)', number_format($metrics['searches']))
                ->description('Product searches')
                ->descriptionIcon('heroicon-o-magnifying-glass')
                ->color('gray'),
        ];
    }
}
