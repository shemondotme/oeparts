<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\CronLog;
use App\Models\EmailLog;
use App\Models\FailedSearchLog;
use App\Models\LoginLog;
use App\Models\SearchLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanOldLogs extends Command
{
    protected $signature = 'logs:clean {--days= : Number of days to retain logs (defaults to search.log_retention_days setting)}';

    protected $description = 'Clean old logs for GDPR compliance (default: 90 days retention)';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) settings('search.log_retention_days', 90);
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning logs older than {$days} days (before {$cutoffDate->toDateString()})...");

        $searchLogsDeleted = DB::table('search_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$searchLogsDeleted} search logs.");

        $failedSearchLogsDeleted = DB::table('failed_search_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$failedSearchLogsDeleted} failed search logs.");

        $loginLogsDeleted = DB::table('login_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$loginLogsDeleted} login logs.");

        $activityLogsDeleted = DB::table('activity_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$activityLogsDeleted} activity logs.");

        $cronLogsDeleted = DB::table('cron_logs')
            ->where('ran_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$cronLogsDeleted} cron logs.");

        $emailLogsDeleted = DB::table('email_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$emailLogsDeleted} email logs.");

        $totalDeleted = $searchLogsDeleted + $failedSearchLogsDeleted + $loginLogsDeleted + 
                        $activityLogsDeleted + $cronLogsDeleted + $emailLogsDeleted;

        $this->info("Total: Deleted {$totalDeleted} old log entries for GDPR compliance.");

        return Command::SUCCESS;
    }
}
