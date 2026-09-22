<?php

namespace App\Filament\Pages\System;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ErrorMonitor extends Page
{
    protected static ?string $slug = 'system/error-monitor';

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

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
            // Reads the actually-configured single-channel destination
            // (falls back to the conventional default) rather than
            // assuming it — also the seam a test overrides to exercise this
            // without ever touching the real dev-environment log file.
            $logPath = config('logging.channels.single.path', storage_path('logs/laravel.log'));
            if (! file_exists($logPath)) {
                return [];
            }

            $handle = fopen($logPath, 'r');
            if ($handle === false) {
                // fopen() returning false and then being passed to fseek()
                // raises a TypeError (PHP 8's stricter internal-function
                // argument checks), not an Exception — the catch below
                // wouldn't even have caught that. Checking here avoids
                // relying on catching an \Error for an expected failure mode.
                throw new \RuntimeException("Could not open {$logPath} for reading.");
            }
            fseek($handle, max(0, filesize($logPath) - 512000));
            $content = stream_get_contents($handle);
            fclose($handle);

            // The ORIGINAL pattern here never matched a single real log
            // line: it required an ISO8601-with-microseconds timestamp
            // ([Y-m-dTH:i:s.uuuuuu]), but Laravel's actual default format
            // (confirmed live against this app's own laravel.log, no custom
            // Monolog formatter configured anywhere) is space-separated
            // with no fractional seconds: [Y-m-d H:i:s]. It also assumed
            // separate "message"/"file"/"line" JSON keys that don't exist —
            // Laravel's default exception context is a single "exception"
            // string in Monolog's own normalized form:
            //   {"exception":"[object] (ClassName(code: N): message at
            //   FILE:LINE)\n[stacktrace]\n#0 ..."}
            // This dashboard has never actually shown a real exception.
            // Rebuilt against that real, confirmed format — the array below
            // reads $match[1]..$match[5] as time/type/message/file/line,
            // exactly the 5 groups this now captures, in that order.
            $pattern = '/\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s+\S+\.(\w+):\s+(.*?)\s*\{"exception":"\[object\]\s*\([^(]*\(code:\s*\d+\):.*?\s+at\s+(.+?):(\d+)\)/i';
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

            $errors = [];
            foreach (array_slice($matches, -50) as $match) {
                $errors[] = [
                    'time' => $match[1],
                    'type' => $match[2],
                    'message' => $match[3],
                    'file' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $match[4]),
                    'line' => $match[5],
                ];
            }

            return array_reverse($errors);
        } catch (\Throwable $e) {
            // This IS the admin's error-monitoring dashboard — silently
            // returning [] here reads as "no errors" (false reassurance)
            // instead of surfacing that the monitor itself is broken.
            // \Throwable, not \Exception: a raw fopen()/fseek() failure can
            // surface as a TypeError (\Error), not just an \Exception.
            Log::error('ErrorMonitor::getExceptionLog() failed to read/parse the log file: '.$e->getMessage());

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
            Log::error('ErrorMonitor::getFailedJobStats() failed: '.$e->getMessage());

            return ['total' => 0, 'by_queue' => []];
        }
    }
}
