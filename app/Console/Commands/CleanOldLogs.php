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
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:clean {--days= : Number of days to retain logs (defaults to search.log_retention_days setting)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean old logs for GDPR compliance (default: 90 days retention)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) settings('search.log_retention_days', 90);
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning logs older than {$days} days (before {$cutoffDate->toDateString()})...");

        // Clean search_logs
        $searchLogsDeleted = DB::table('search_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$searchLogsDeleted} search logs.");

        // Clean failed_search_logs
        $failedSearchLogsDeleted = DB::table('failed_search_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$failedSearchLogsDeleted} failed search logs.");

        // Clean login_logs
        $loginLogsDeleted = DB::table('login_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$loginLogsDeleted} login logs.");

        // Clean activity_logs
        $activityLogsDeleted = DB::table('activity_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$activityLogsDeleted} activity logs.");

        // Clean cron_logs
        $cronLogsDeleted = DB::table('cron_logs')
            ->where('ran_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$cronLogsDeleted} cron logs.");

        // Clean email_logs
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
