<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin caching helper — used by widgets that need to read live data
 * without hammering the database. Each cache key is namespaced and
 * tagged so it can be selectively invalidated.
 *
 * NOTE: never Cache::flush() — only forget() specific keys.
 *
 * For local development: set CACHE_STORE=file (or database) in .env to enable
 * widget caching across page reloads. Array driver is used in tests and skips cache.
 * Production should use Redis or a persistent cache driver.
 */
final class AdminCacheService
{
    public const TAG_DASHBOARD = 'admin.dashboard';
    public const TAG_HEALTH = 'admin.health';
    public const TAG_NAV = 'admin.nav';

    /** Default TTL: 60 seconds — most widget data is fine to be 1 min stale */
    public const DEFAULT_TTL = 60;

    /** Short TTL: 15s — for very live widgets (system health, alerts) */
    public const SHORT_TTL = 15;

    /** Long TTL: 5min — for slow-changing data (recent activity, logs) */
    public const LONG_TTL = 300;

    /**
     * Tests run on the array driver, which is normally bypassed; setting this
     * true lets cache-behavior tests exercise the remember/forget paths.
     */
    public static bool $bypassArrayDriverCheck = false;

    public static function dashboard(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $cacheKey = 'admin:dashboard:' . $key;

        if (self::shouldSkipCache()) {
            return $callback();
        }

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    public static function health(string $key, callable $callback, int $ttl = self::SHORT_TTL): mixed
    {
        $cacheKey = 'admin:health:' . $key;

        if (self::shouldSkipCache()) {
            return $callback();
        }

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    public static function forget(string $key): void
    {
        if (! self::shouldSkipCache()) {
            Cache::forget('admin:dashboard:' . $key);
            Cache::forget('admin:health:' . $key);
        }
    }

    private static function shouldSkipCache(): bool
    {
        if (self::$bypassArrayDriverCheck) {
            return false;
        }

        $driver = config('cache.default');

        if ($driver === 'array') {
            return true;
        }

        return false;
    }

    /**
     * Quick DB ping for the health widget.
     */
    public static function databasePingMs(): int
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            return -1;
        }
        return (int) round((microtime(true) - $start) * 1000);
    }
}
