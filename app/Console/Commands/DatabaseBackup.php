<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup {--output= : Backup file output path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a database backup using mysqldump';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database backup...');

        $startTime = microtime(true);

        // Get database configuration
        $dbHost = config('database.connections.mysql.host');
        $dbPort = config('database.connections.mysql.port', '3306');
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');

        // Determine output path
        $outputPath = $this->option('output');
        if (!$outputPath) {
            $timestamp = now()->format('Y-m-d-His');
            $outputPath = storage_path("app/backups/{$dbName}-{$timestamp}.sql");
        }

        // Ensure backup directory exists
        $backupDir = dirname($outputPath);
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Build mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($outputPath)
        );

        // Execute backup
        $this->info("Backing up database to: {$outputPath}");

        $returnCode = 0;
        $output = [];
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMessage = implode("\n", $output);
            $this->error("Database backup failed: {$errorMessage}");

            // Log failure
            $this->logCronResult('database_backup', 'failed', 0, $errorMessage);

            return Command::FAILURE;
        }

        // Calculate duration
        $duration = (int) ((microtime(true) - $startTime) * 1000);

        // Get file size
        $fileSize = filesize($outputPath);
        $fileSizeFormatted = $this->formatFileSize($fileSize);

        $this->info("Backup completed successfully: {$fileSizeFormatted}");
        $this->info("Duration: {$duration}ms");

        // Log success
        $this->logCronResult('database_backup', 'success', $duration, "Backup created: {$outputPath} ({$fileSizeFormatted})");

        // Clean up old backups (keep last 7 days)
        $this->cleanupOldBackups();

        return Command::SUCCESS;
    }

    /**
     * Format file size to human-readable format.
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Clean up old backup files (older than 7 days).
     */
    private function cleanupOldBackups(): void
    {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            return;
        }

        $files = glob($backupDir . '/*.sql');
        $cutoffTime = time() - (7 * 24 * 60 * 60); // 7 days ago
        $deletedCount = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->info("Cleaned up {$deletedCount} old backup files.");
        }
    }

    /**
     * Log cron job result to database.
     */
    private function logCronResult(string $jobName, string $status, int $durationMs, string $output): void
    {
        try {
            DB::table('cron_logs')->insert([
                'job_name' => $jobName,
                'status' => $status,
                'duration_ms' => $durationMs,
                'output' => $output,
                'ran_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Ignore logging errors
            $this->warn("Failed to log cron result: " . $e->getMessage());
        }
    }
}
