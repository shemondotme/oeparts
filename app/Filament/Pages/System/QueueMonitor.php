<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class QueueMonitor extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Queue Monitor';

    protected ?string $subheading = 'Real-time queue depth, throughput, and failure rates.';

    protected string $view = 'filament.pages.system.queue-monitor';

    protected static ?string $pollingInterval = '30s';

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin->hasRole('super_admin') || $admin->hasPermissionTo('view system information');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-server-stack';
    }

    public static function getNavigationLabel(): string
    {
        return 'Queue Monitor';
    }

    public static function getNavigationSort(): ?int
    {
        return 45;
    }

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public function getQueueStats(): array
    {
        return Cache::remember('queue_monitor_stats', 30, function () {
            $jobsTable = config('queue.connections.database.table', 'jobs');
            $failedTable = 'failed_jobs';

            $pendingByQueue = [];
            try {
                $pendingByQueue = DB::table($jobsTable)
                    ->select('queue', DB::raw('COUNT(*) as count'))
                    ->groupBy('queue')
                    ->pluck('count', 'queue')
                    ->toArray();
            } catch (\Exception $e) {
            }

            $totalPending = array_sum($pendingByQueue);

            $processing = 0;
            try {
                $processing = DB::table($jobsTable)
                    ->where('reserved_at', '>', now()->subMinutes(5))
                    ->count();
            } catch (\Exception $e) {
            }

            $failedLast24h = 0;
            $failedByQueue = [];
            try {
                $failedLast24h = DB::table($failedTable)
                    ->where('failed_at', '>=', now()->subDay())
                    ->count();

                $failedByQueue = DB::table($failedTable)
                    ->where('failed_at', '>=', now()->subDay())
                    ->select('queue', DB::raw('COUNT(*) as count'))
                    ->groupBy('queue')
                    ->pluck('count', 'queue')
                    ->toArray();
            } catch (\Exception $e) {
            }

            $completedLastHour = 0;
            try {
                $completedLastHour = DB::table('job_batches')
                    ->where('finished_at', '>=', now()->subHour())
                    ->sum('total_jobs');
            } catch (\Exception $e) {
            }

            return [
                'total_pending' => $totalPending,
                'processing' => $processing,
                'failed_24h' => $failedLast24h,
                'completed_hour' => (int) $completedLastHour,
                'by_queue' => $pendingByQueue,
                'failed_by_queue' => $failedByQueue,
            ];
        });
    }

    public function getRecentFailedJobs(): array
    {
        try {
            return DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(20)
                ->get()
                ->map(fn ($job) => [
                    'id' => $job->id,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'payload' => $this->parsePayload($job->payload),
                    'failed_at' => $job->failed_at,
                    'exception' => substr($job->exception ?? '', 0, 200),
                ])
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function parsePayload(string $payload): string
    {
        $data = json_decode($payload, true);
        return $data['displayName'] ?? $data['job'] ?? 'Unknown Job';
    }
}
