<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ErrorMonitor extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Error Monitor';

    protected ?string $subheading = 'Recent application errors and exception tracking.';

    protected string $view = 'filament.pages.system.error-monitor';

    protected static ?string $pollingInterval = '30s';

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin && ($admin->hasRole('super_admin') || $admin->hasPermissionTo('view system information'));
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public static function getNavigationLabel(): string
    {
        return 'Error Monitor';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public function getErrorStats(): array
    {
        return Cache::remember('error_monitor_stats', 30, function () {
            $exceptions = $this->getExceptionLog();
            $failedJobs = $this->getFailedJobStats();

            return [
                'total_exceptions_24h' => count($exceptions),
                'total_failed_jobs_24h' => $failedJobs['total'],
                'unique_exceptions' => count(array_unique(array_column($exceptions, 'class'))),
                'by_exception' => collect($exceptions)->groupBy('class')->map(fn ($items) => $items->count())->toArray(),
            ];
        });
    }

    public function getExceptionLog(): array
    {
        try {
            $logPath = storage_path('logs/laravel.log');
            if (!file_exists($logPath)) {
                return [];
            }

            $handle = fopen($logPath, 'r');
            fseek($handle, max(0, filesize($logPath) - 512000));
            $content = stream_get_contents($handle);
            fclose($handle);

            $pattern = '/\[(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})\.\d+\].*?(?:exception|error).*?(?:"message"|"message":\s*")(.*?)(?:"|").*?(?:"file"|"file":\s*")(.*?)(?:"|").*?(?:"line"|"line":\s*)(\d+)/i';
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

            $errors = [];
            foreach (array_slice($matches, -50) as $match) {
                $errors[] = [
                    'time' => $match[1],
                    'type' => $match[2],
                    'message' => $match[3],
                    'file' => isset($match[4]) ? str_replace(base_path() . DIRECTORY_SEPARATOR, '', $match[4]) : '',
                    'line' => $match[5] ?? 0,
                ];
            }

            return array_reverse($errors);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getFailedJobStats(): array
    {
        try {
            $total = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();

            $byQueue = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->select('queue', DB::raw('COUNT(*) as count'))
                ->groupBy('queue')
                ->pluck('count', 'queue')
                ->toArray();

            return [
                'total' => $total,
                'by_queue' => $byQueue,
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'by_queue' => []];
        }
    }
}
