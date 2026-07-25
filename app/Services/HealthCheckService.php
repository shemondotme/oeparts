<?php

namespace App\Services;

use App\Enums\AdminNotificationCategory;
use App\Filament\Pages\System\HealthCheckDashboard;
use App\Models\HealthCheckSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * HealthCheckService — comprehensive system health verification.
 *
 * Used by the admin Health Check page (App\Filament\Widgets\System\HealthCheckStats,
 * via snapshot()) and the health:snapshot scheduled command. NOT used by the
 * public /health endpoint — App\Http\Controllers\HealthController has its own
 * independent, deliberately minimal database/cache checks for uptime monitors,
 * so this service's return shape is free to evolve without touching that
 * external contract.
 *
 * Checks: database, cache/Redis, queue, storage, scheduler, compiled assets, backup age.
 */
class HealthCheckService
{
    /** Minimum gap between persisted snapshots/alerts, shared across every caller. */
    private const SNAPSHOT_THROTTLE_SECONDS = 60;

    /**
     * Run all health checks and return the results. Stateless — safe to call
     * as often as needed (e.g. every widget poll); never writes history or
     * fires alerts. Use snapshot() for that.
     *
     * @return array{status: string, version: string, timestamp: string, checks: array<string, array{status: string, detail: string, response_time_ms: ?int}>}
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

        $status = in_array('fail', array_column($checks, 'status'), true) ? 'degraded' : 'ok';

        return [
            'status'    => $status,
            'version'   => $this->version(),
            'timestamp' => now()->toIso8601String(),
            'checks'    => $checks,
        ];
    }

    /**
     * Run all checks, and — at most once per SNAPSHOT_THROTTLE_SECONDS across
     * every process (widget polling, the health:snapshot scheduled command) —
     * persist a HealthCheckSnapshot row per check and notify all admins on an
     * ok -> non-ok transition. The Cache::add() lock is atomic set-if-absent,
     * so whichever caller wins the race does the write/notify; everyone else
     * just returns fresh live data with no side effects. This is what keeps N
     * admins watching the page from writing N history rows, and keeps a
     * single failure transition from ever notifying twice.
     *
     * @return array{status: string, version: string, timestamp: string, checks: array}
     */
    public function snapshot(): array
    {
        $result = $this->runAll();

        if (! Cache::add('health_check:snapshot_lock', 1, self::SNAPSHOT_THROTTLE_SECONDS)) {
            return $result;
        }

        foreach ($result['checks'] as $key => $check) {
            $previous = HealthCheckSnapshot::forCheck($key)
                ->orderByDesc('checked_at')
                ->orderByDesc('id')
                ->first();

            HealthCheckSnapshot::create([
                'check_key'        => $key,
                'status'           => $check['status'],
                'detail'           => $check['detail'],
                'response_time_ms' => $check['response_time_ms'],
                'checked_at'       => now(),
            ]);

            if ($previous?->status === 'ok' && $check['status'] !== 'ok') {
                app(AdminNotificationService::class)->createForAll(
                    AdminNotificationCategory::System,
                    ucfirst($key) . ' health check failing',
                    "Status changed from ok to {$check['status']}: {$check['detail']}",
                    HealthCheckDashboard::getUrl(),
                );
            }
        }

        return $result;
    }

    /**
     * Check database connectivity.
     *
     * @return array{status: string, detail: string, response_time_ms: ?int}
     */
    public function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $ms = (int) round((microtime(true) - $start) * 1000);

            // SHOW TABLES is MySQL-specific (the production/dev driver) and
            // throws under sqlite (the test driver) — connectivity is the
            // part that actually matters for 'ok'/'fail', so a table-count
            // failure degrades the detail message rather than the status.
            try {
                $detail = count(DB::select('SHOW TABLES')) . ' tables';
            } catch (\Throwable) {
                $detail = 'connected';
            }

            return ['status' => 'ok', 'detail' => $detail, 'response_time_ms' => $ms];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage(), 'response_time_ms' => null];
        }
    }

    /**
     * Check cache/Redis connectivity.
     *
     * @return array{status: string, detail: string, response_time_ms: ?int}
     */
    public function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_ping_' . uniqid();
            Cache::put($key, 'ok', 1);
            $result = Cache::get($key);
            Cache::forget($key);
            $ms = (int) round((microtime(true) - $start) * 1000);

            return $result === 'ok'
                ? ['status' => 'ok', 'detail' => 'read/write ok', 'response_time_ms' => $ms]
                : ['status' => 'fail', 'detail' => 'read/write mismatch', 'response_time_ms' => $ms];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage(), 'response_time_ms' => null];
        }
    }

    /**
     * Check queue connection.
     *
     * @return array{status: string, detail: string, response_time_ms: ?int}
     */
    public function checkQueue(): array
    {
        try {
            $connection = config('queue.default');

            // Redis queue shares its connection with the cache check —
            // reuse that round trip instead of pinging Redis twice.
            if ($connection === 'redis') {
                $result = $this->checkCache();

                return [
                    'status'           => $result['status'],
                    'detail'           => $result['status'] === 'ok' ? 'redis reachable' : $result['detail'],
                    'response_time_ms' => $result['response_time_ms'],
                ];
            }

            return ['status' => 'ok', 'detail' => "driver: {$connection}", 'response_time_ms' => null];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage(), 'response_time_ms' => null];
        }
    }

    /**
     * Check storage is writable.
     *
     * @return array{status: string, detail: string, response_time_ms: ?int}
     */
    public function checkStorage(): array
    {
        try {
            $start = microtime(true);
            $testFile = 'health_test_' . uniqid() . '.tmp';
            Storage::disk('local')->put($testFile, 'ok');
            $contents = Storage::disk('local')->get($testFile);
            Storage::disk('local')->delete($testFile);
            $ms = (int) round((microtime(true) - $start) * 1000);

            return $contents === 'ok'
                ? ['status' => 'ok', 'detail' => 'writable', 'response_time_ms' => $ms]
                : ['status' => 'fail', 'detail' => 'write/read mismatch', 'response_time_ms' => $ms];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage(), 'response_time_ms' => null];
        }
    }

    /**
     * Check scheduler heartbeat from cache.
     *
     * @return array{status: string, detail: string, response_time_ms: ?int}
     */
    public function checkScheduler(): array
    {
        try {
            $heartbeat = Cache::get('scheduler_heartbeat');
            if (!$heartbeat) {
                return ['status' => 'unknown', 'detail' => 'no heartbeat recorded', 'response_time_ms' => null];
            }

            // abs(): Carbon 3's diffInSeconds() returns a SIGNED value
            // (negative for a past date) unlike Carbon 2's always-absolute
            // default — the heartbeat is always in the past here, so without
            // abs() the threshold comparison below could never report 'stale'.
            $seconds = abs(now()->diffInSeconds($heartbeat));
            $thresholdMinutes = (int) settings('dashboard.scheduler_stale_minutes', 3);

            return $seconds <= $thresholdMinutes * 60
                ? ['status' => 'ok', 'detail' => "beat {$seconds}s ago", 'response_time_ms' => null]
                : ['status' => 'stale', 'detail' => "{$seconds}s since last beat", 'response_time_ms' => null];
        } catch (\Throwable) {
            return ['status' => 'unknown', 'detail' => 'unable to read heartbeat', 'response_time_ms' => null];
        }
    }

    /**
     * Age of the most recent successful Backup Engine backup (Module 21, Chunk 2.6).
     * 'ok' if within the staleness window, 'stale' if older, 'none' if there has
     * never been a successful backup. Never returns 'fail' — a missing backup is
     * an operational warning, not a system fault (so it won't mark runAll() degraded).
     *
     * @return array{status: string, detail: string, response_time_ms: ?int}
     */
    public function checkLastBackup(): array
    {
        try {
            $at = $this->lastBackupAt();
            if (! $at) {
                return ['status' => 'none', 'detail' => 'no backup recorded', 'response_time_ms' => null];
            }

            $threshold = (int) settings('dashboard.backup_stale_hours', 26);
            $hours = abs((int) round(now()->diffInHours($at)));

            return $at->greaterThan(now()->subHours($threshold))
                ? ['status' => 'ok', 'detail' => "{$hours}h ago", 'response_time_ms' => null]
                : ['status' => 'stale', 'detail' => "{$hours}h ago", 'response_time_ms' => null];
        } catch (\Throwable) {
            return ['status' => 'unknown', 'detail' => 'unable to read backup status', 'response_time_ms' => null];
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
     *
     * @return array{status: string, detail: string, response_time_ms: ?int}
     */
    public function checkAssets(): array
    {
        try {
            $manifest = public_path('build/manifest.json');

            return file_exists($manifest)
                ? ['status' => 'ok', 'detail' => 'manifest present', 'response_time_ms' => null]
                : ['status' => 'missing', 'detail' => 'manifest missing', 'response_time_ms' => null];
        } catch (\Throwable) {
            return ['status' => 'fail', 'detail' => 'unable to read manifest', 'response_time_ms' => null];
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
