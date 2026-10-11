<?php

namespace App\Console\Commands;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CleanOldLogs extends Command
{
    /** Rows deleted per statement — keeps each DELETE short on 1M+ row tables (no long locks, no replica lag spike). */
    private const CHUNK = 5000;

    protected $signature = 'logs:clean
        {--days= : Number of days to retain logs (defaults to security.log_retention_days setting)}
        {--force : Allow --days below 1 (0 deletes every log row, including today\'s)}';

    protected $description = 'Clean old logs for GDPR compliance (default: 90 days retention) and prune old install/update log files';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) settings('security.log_retention_days', 90);

        // `--days=0` (or a typo that casts to 0, or a negative value) puts the cutoff
        // at now/in the future and would wipe EVERY row including today's audit trail.
        // Refuse unless the operator says --force.
        if ($days < 1 && ! $this->option('force')) {
            $this->error("Refusing to run with a retention of {$days} day(s): that would delete every log row. Pass --force if you really mean it.");

            return Command::FAILURE;
        }

        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning logs older than {$days} days (before {$cutoffDate->toDateString()})...");

        $searchLogsDeleted = $this->pruneBefore('search_logs', 'created_at', $cutoffDate);
        $this->info("Deleted {$searchLogsDeleted} search logs.");

        $failedSearchLogsDeleted = $this->pruneBefore('failed_search_logs', 'created_at', $cutoffDate);
        $this->info("Deleted {$failedSearchLogsDeleted} failed search logs.");

        $loginLogsDeleted = $this->pruneBefore('login_logs', 'created_at', $cutoffDate);
        $this->info("Deleted {$loginLogsDeleted} login logs.");

        $activityLogsDeleted = $this->pruneBefore('activity_logs', 'created_at', $cutoffDate);
        $this->info("Deleted {$activityLogsDeleted} activity logs.");

        $cronLogsDeleted = $this->pruneBefore('cron_logs', 'ran_at', $cutoffDate);
        $this->info("Deleted {$cronLogsDeleted} cron logs.");

        // email_logs is aged by sent_at (NOT NULL, indexed). Its created_at column is
        // NULL on every row the app writes (the model has no timestamps and the
        // LogEmailSent/LogEmailFailed listeners only set sent_at), so filtering on
        // created_at here silently deleted nothing, ever.
        $emailLogsDeleted = $this->pruneBefore('email_logs', 'sent_at', $cutoffDate);
        $this->info("Deleted {$emailLogsDeleted} email logs.");

        $totalDeleted = $searchLogsDeleted + $failedSearchLogsDeleted + $loginLogsDeleted +
                        $activityLogsDeleted + $cronLogsDeleted + $emailLogsDeleted;

        $this->info("Total: Deleted {$totalDeleted} old log entries for GDPR compliance.");

        // Install/update log files (storage/logs/install-*.log, updates-*.log) are written
        // via a plain "single" Monolog driver with no rotation, so they never self-prune —
        // clean them here on the same daily schedule as the DB-backed logs above.
        // Read through config, never env() directly: once `config:cache` runs, env()
        // returns null outside config files and the value would silently fall back to 30.
        $fileRetentionDays = (int) config('logging.channels.updates.days', 30);
        $fileCutoff = now()->subDays($fileRetentionDays)->getTimestamp();

        $logFilesDeleted = 0;
        foreach (['install-*.log', 'updates-*.log'] as $pattern) {
            foreach (File::glob(storage_path('logs/'.$pattern)) as $path) {
                if (File::lastModified($path) < $fileCutoff) {
                    File::delete($path);
                    $logFilesDeleted++;
                }
            }
        }
        $this->info("Deleted {$logFilesDeleted} old install/update log files (older than {$fileRetentionDays} days).");

        return Command::SUCCESS;
    }

    /**
     * Delete rows older than $cutoff in short id-keyed chunks instead of one unbounded
     * DELETE. On a log table with millions of rows a single statement holds locks for
     * minutes, bloats the binlog in one transaction and stalls replicas.
     *
     * Deliberately NOT `DELETE ... LIMIT` (unsafe under statement-based replication):
     * pick a bounded id list, then delete exactly those ids.
     */
    private function pruneBefore(string $table, string $column, CarbonInterface $cutoff): int
    {
        $total = 0;

        do {
            $ids = DB::table($table)
                ->where($column, '<', $cutoff)
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = DB::table($table)->whereIn('id', $ids->all())->delete();
            $total += $deleted;

            // Another process removed exactly this batch between our two statements; the
            // next SELECT will return a fresh set, but never spin on a zero-progress loop.
            if ($deleted === 0) {
                break;
            }
        } while ($ids->count() === self::CHUNK);

        return $total;
    }
}
