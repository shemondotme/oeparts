<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\System\FailedJobsPage;
use App\Filament\Pages\System\QueueMonitor;
use App\Filament\Resources\EmailLogResource;
use App\Filament\Resources\SearchLogResource;
use App\Filament\Widgets\Concerns\HasMonitoringVisuals;
use App\Filament\Widgets\Concerns\HasWidgetRoles;
use App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestMetricsWidget extends BaseWidget
{
    public function getDescription(): ?string
    {
        return 'Queue, email, and search activity (last hour)';
    }

    use HasMonitoringVisuals;
    use HasWidgetRoles;
    use InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    // Full-width so the 4 stats lay out horizontally (System Health strip).
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -14;

    protected ?string $heading = 'Activity Metrics';

    protected function getStats(): array
    {
        $metrics = $this->cachedWidgetData(function () {
            $jobsTable = config('queue.connections.database.table', 'jobs');
            $failedTable = 'failed_jobs';

            // Each metric below is independently try/caught (one broken
            // query must not blank out the other three) — but every catch
            // used to be completely silent, so a DB error just rendered as
            // a falsely-reassuring "0" on this exact activity-monitoring
            // widget, with no trace anywhere that the metric itself was
            // broken vs. genuinely zero.
            $pendingJobs = 0;
            try {
                $pendingJobs = DB::table($jobsTable)->count();
            } catch (\Exception $e) {
                Log::warning('RequestMetricsWidget: pending-jobs count failed: '.$e->getMessage());
            }

            $failedJobs = 0;
            try {
                $failedJobs = DB::table($failedTable)
                    ->where('failed_at', '>=', now()->subHour())
                    ->count();
            } catch (\Exception $e) {
                Log::warning('RequestMetricsWidget: failed-jobs count failed: '.$e->getMessage());
            }

            $emailsSent = 0;
            try {
                $emailsSent = DB::table('email_logs')
                    ->where('created_at', '>=', now()->subHour())
                    ->count();
            } catch (\Exception $e) {
                Log::warning('RequestMetricsWidget: emails-sent count failed: '.$e->getMessage());
            }

            $searches = 0;
            try {
                $searches = DB::table('search_logs')
                    ->where('created_at', '>=', now()->subHour())
                    ->count();
            } catch (\Exception $e) {
                Log::warning('RequestMetricsWidget: searches count failed: '.$e->getMessage());
            }

            return [
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'emails_sent' => $emailsSent,
                'searches' => $searches,
                'failed_trend' => $this->hourlyTrend($failedTable, 'failed_at', 12),
                'emails_trend' => $this->hourlyTrend('email_logs', 'created_at', 12),
                'searches_trend' => $this->hourlyTrend('search_logs', 'created_at', 12),
            ];
        });

        $failedLevel = $metrics['failed_jobs'] > 5 ? 'danger' : ($metrics['failed_jobs'] > 0 ? 'warning' : null);

        return [
            Stat::make('Pending Jobs', number_format($metrics['pending_jobs']))
                ->description($metrics['pending_jobs'] > 0 ? 'In queue' : 'All clear')
                ->descriptionIcon('heroicon-o-clock')
                ->chart($this->rollingSamples('req_pending', $metrics['pending_jobs']))
                ->chartColor($metrics['pending_jobs'] > 50 ? 'warning' : 'gray')
                ->color($metrics['pending_jobs'] > 50 ? 'warning' : 'gray')
                ->extraAttributes($this->alertAccent($metrics['pending_jobs'] > 50 ? 'warning' : null))
                ->url(QueueMonitor::getUrl()),
            Stat::make('Failed (1h)', number_format($metrics['failed_jobs']))
                ->description($metrics['failed_jobs'] > 0 ? 'Needs attention' : 'No failures')
                ->descriptionIcon('heroicon-o-x-circle')
                ->chart($metrics['failed_trend'])
                ->chartColor($metrics['failed_jobs'] > 0 ? 'danger' : 'success')
                ->color($metrics['failed_jobs'] > 0 ? 'danger' : 'success')
                ->url(FailedJobsPage::getUrl())
                ->extraAttributes($this->alertAccent($failedLevel)),
            Stat::make('Emails Sent (1h)', number_format($metrics['emails_sent']))
                ->description('Last hour')
                ->descriptionIcon('heroicon-o-envelope')
                ->chart($metrics['emails_trend'])
                ->chartColor('info')
                ->color('info')
                ->url(EmailLogResource::getUrl('index')),
            Stat::make('Searches (1h)', number_format($metrics['searches']))
                ->description('Product searches')
                ->descriptionIcon('heroicon-o-magnifying-glass')
                ->chart($metrics['searches_trend'])
                ->chartColor('gray')
                ->color('gray')
                ->url(SearchLogResource::getUrl('index')),
        ];
    }
}
