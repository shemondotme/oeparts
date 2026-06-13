<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CacheService — key-based cache helpers for OeParts.
 *
 * NEVER call Cache::flush() — it destroys all sessions.
 * Always use specific key patterns to invalidate cache.
 */
class CacheService
{
    // ── Section cache ─────────────────────────────────────────────────────────

    /**
     * Remember a section result, respecting the cache_sections setting.
     *
     * @param  string  $location  e.g. 'homepage', 'sidebar'
     * @param  callable  $callback  The closure that fetches the data
     */
    public function rememberSection(string $location, callable $callback): mixed
    {
        if (! settings('performance.cache_sections', true)) {
            return $callback();
        }

        $ttl = (int) settings('performance.cache_ttl_sections', 60);
        $key = $this->sectionKey($location);

        try {
            return Cache::remember($key, now()->addMinutes($ttl), $callback);
        } catch (\Exception $e) {
            Log::error('Cache rememberSection failed', [
                'location' => $location,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return $callback();
        }
    }

    /**
     * Invalidate the cache for a specific section location.
     */
    public function forgetSections(string $location): void
    {
        Log::debug('Cache invalidated', ['key' => $this->sectionKey($location)]);
        Cache::forget($this->sectionKey($location));
    }

    /**
     * Invalidate section caches for multiple locations at once.
     */
    public function forgetManySections(array $locations): void
    {
        foreach ($locations as $location) {
            $this->forgetSections($location);
        }
    }

    // ── Manufacturer cache ────────────────────────────────────────────────────

    /**
     * Remember the manufacturer list.
     */
    public function rememberManufacturers(callable $callback): mixed
    {
        if (! settings('performance.cache_manufacturers', true)) {
            return $callback();
        }

        $ttl = (int) settings('performance.cache_ttl_manufacturers', 60);

        return Cache::remember('manufacturers.active', now()->addMinutes($ttl), $callback);
    }

    /**
     * Invalidate the manufacturer list cache.
     */
    public function forgetManufacturers(): void
    {
        Cache::forget('manufacturers.active');
    }

    // ── Hero stats cache ──────────────────────────────────────────────────────

    /**
     * Remember hero spec-panel counts (parts, manufacturers, cross-refs).
     * TTL: 6 hours — these counts change infrequently.
     */
    public function rememberHeroStats(callable $callback): mixed
    {
        return Cache::remember('hero.stats', now()->addHours(6), $callback);
    }

    /**
     * Invalidate hero stats cache — call after bulk product/manufacturer imports.
     */
    public function forgetHeroStats(): void
    {
        Cache::forget('hero.stats');
    }

    /**
     * Remember popular OEM numbers for the hero "INDEXED:" strip.
     * TTL: 1 hour — refreshes as search trends shift.
     */
    public function rememberPopularOems(callable $callback): mixed
    {
        return Cache::remember('hero.popular_oems', now()->addHour(), $callback);
    }

    /**
     * Invalidate popular OEMs cache — call after bulk product imports.
     */
    public function forgetPopularOems(): void
    {
        Cache::forget('hero.popular_oems');
    }

    // ── Settings cache ────────────────────────────────────────────────────────

    /**
     * Invalidate a specific settings group cache.
     * Delegates to SettingsService to keep the cache key consistent.
     */
    public function forgetSettingsGroup(string $group): void
    {
        Cache::forget("settings.{$group}");
    }

    // ── Generic helpers ───────────────────────────────────────────────────────

    /**
     * Remember any value under a namespaced key.
     *
     * @param  string  $key  Cache key
     * @param  int  $minutes  TTL in minutes
     * @param  callable  $callback  Data fetcher
     */
    public function remember(string $key, int $minutes, callable $callback): mixed
    {
        return Cache::remember($key, now()->addMinutes($minutes), $callback);
    }

    /**
     * Forget a single arbitrary cache key.
     */
    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function sectionKey(string $location): string
    {
        return "sections.{$location}";
    }
}
