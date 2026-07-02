<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\File;

/**
 * HealthCheckService — comprehensive system health verification.
 *
 * Used by the /health endpoint and the admin health dashboard.
 * Checks: database, cache/Redis, queue, storage, scheduler, compiled assets.
 */
class HealthCheckService
{
    /**
     * Run all health checks and return the results.
     *
     * @return array{status: string, version: string, timestamp: string, checks: array}
     */
    public function runAll(): array
    {
        $checks = [
            'database'  => $this->checkDatabase(),
            'cache'     => $this->checkCache(),
            'queue'     => $this->checkQueue(),
            'storage'   => $this->checkStorage(),
            'scheduler' => $this->checkScheduler(),
            'assets'    => $this->checkAssets(),
            'backup'    => $this->checkLastBackup(),
        ];

        $status = in_array('fail', $checks, true) ? 'degraded' : 'ok';

        return [
            'status'    => $status,
            'version'   => $this->version(),
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ];
    }

    /**
     * Check database connectivity.
     */
    public function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    /**
     * Check cache/Redis connectivity.
     */
    public function checkCache(): string
    {
        try {
            $key = 'health_ping_' . uniqid();
            Cache::put($key, 'ok', 1);
            $result = Cache::get($key);
            Cache::forget($key);
            return $result === 'ok' ? 'ok' : 'fail';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    /**
     * Check queue connection.
     */
    public function checkQueue(): string
    {
        try {
            $connection = config('queue.default');
            if (in_array($connection, ['sync', 'database'], true)) {
                return 'ok';
            }
            // For Redis queue, just verify Redis is reachable
            if ($connection === 'redis') {
                return $this->checkCache();
            }
            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    /**
     * Check storage is writable.
     */
    public function checkStorage(): string
    {
        try {
            $testFile = 'health_test_' . uniqid() . '.tmp';
            Storage::disk('local')->put($testFile, 'ok');
            $contents = Storage::disk('local')->get($testFile);
            Storage::disk('local')->delete($testFile);
            return $contents === 'ok' ? 'ok' : 'fail';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    /**
     * Check scheduler heartbeat from cache.
     */
    public function checkScheduler(): string
    {
        try {
            $heartbeat = Cache::get('scheduler_heartbeat');
            if (!$heartbeat) {
                return 'unknown';
            }
            // Heartbeat should be < 3 minutes old
            $diff = now()->diffInMinutes($heartbeat);
            return $diff <= 3 ? 'ok' : 'stale';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Age of the most recent successful Backup Engine backup (Module 21, Chunk 2.6).
     * 'ok' if within the staleness window, 'stale' if older, 'none' if there has
     * never been a successful backup. Never returns 'fail' — a missing backup is
     * an operational warning, not a system fault (so it won't mark /health degraded).
     */
    public function checkLastBackup(): string
    {
        try {
            $at = $this->lastBackupAt();
            if (! $at) {
                return 'none';
            }

            $threshold = (int) settings('dashboard.backup_stale_hours', 26);

            return $at->greaterThan(now()->subHours($threshold)) ? 'ok' : 'stale';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /** Timestamp of the newest successful, un-pruned backup, or null. */
    public function lastBackupAt(): ?\Illuminate\Support\Carbon
    {
        $run = \App\Models\BackupRun::query()
            ->successful()
            ->whereNull('meta->pruned_at')
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();

        return $run?->finished_at;
    }

    /**
     * Check compiled assets exist.
     */
    public function checkAssets(): string
    {
        try {
            $manifest = public_path('build/manifest.json');
            return file_exists($manifest) ? 'ok' : 'missing';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    /**
     * Get the application version from version.json.
     */
    private function version(): string
    {
        $path = base_path('version.json');
        if (!file_exists($path)) {
            return 'unknown';
        }
        $data = json_decode(file_get_contents($path), true);
        return $data['version'] ?? 'unknown';
    }
}