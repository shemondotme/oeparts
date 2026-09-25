<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        try {
            $value = (int) Cache::remember('nav:badge:'.$key, $ttl, fn (): int => (int) $callback());
        } catch (QueryException $e) {
            // A badge is a courtesy hint and must never take the whole admin panel
            // down with it. The realistic trigger is the window right after a
            // self-update swaps in new code but before its migrations have run: a
            // badge for a table the release adds (e.g. product_reviews) then throws
            // "table doesn't exist" from the sidebar on EVERY admin page — including
            // the very page that would finish the update. Not cached (remember()
            // only stores a successful result), so the badge returns as soon as the
            // schema catches up.
            Log::warning('Navigation badge "'.$key.'" skipped: '.$e->getMessage());

            return null;
        }

        return $value > 0 ? (string) $value : null;
    }
}
