<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cached navigation-badge counts for the admin sidebar.
 *
 * The sidebar renders every resource's getNavigationBadge() on every page
 * load, so an uncached COUNT per resource meant dozens of queries per request.
 * Wrapping each count here caches it briefly (default 60s) — the badges are
 * "roughly live" work-queue hints, so short staleness is fine.
 *
 * Returns the count as a string for Filament's ?string badge contract, or
 * null when zero so the badge is hidden entirely (no "0" clutter).
 */
class NavBadge
{
    public static function count(string $key, \Closure $callback, int $ttl = 60): ?string
    {
        $value = (int) Cache::remember('nav:badge:' . $key, $ttl, fn (): int => (int) $callback());

        return $value > 0 ? (string) $value : null;
    }
}
