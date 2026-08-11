<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

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
     * Invalidate the manufacturer list cache(s).
     */
    public function forgetManufacturers(): void
    {
        Cache::forget('manufacturers.active');
        Cache::forget('manufacturers.active.all');
    }

    /**
     * Remember EVERY active manufacturer (with logo), unfiltered/unpaginated
     * and locale-independent — the source collection for the /brands
     * directory page. ManufacturerController::index() used to run a fresh
     * DB query on every single visit (letter-filter + a locale-aware
     * ORDER BY on a JSON_EXTRACT expression, which can't use an index) —
     * this page is linked from the nav on every single page of the site.
     * Caching once and doing the locale-aware sort/letter-filter/pagination
     * in PHP on this collection avoids both the non-indexable DB sort and a
     * combinatorial explosion of per-locale/per-letter/per-page cache keys.
     * Invalidated the same way as manufacturers.active — same
     * ManufacturerObserver/ProductObserver call sites, no new wiring needed.
     */
    public function rememberAllActiveManufacturers(callable $callback): mixed
    {
        if (! settings('performance.cache_manufacturers', true)) {
            return $callback();
        }

        $ttl = (int) settings('performance.cache_ttl_manufacturers', 60);

        return Cache::remember('manufacturers.active.all', now()->addMinutes($ttl), $callback);
    }

    /**
     * Remember the active-product count per manufacturer for the given ids
     * (featured-brands homepage tile: "N pcs" under each logo) — was a raw,
     * uncached GROUP BY COUNT query embedded directly in the Blade component,
     * run fresh on every single homepage render regardless of the section
     * list itself already being cached above.
     *
     * Keyed by SearchService::cacheVersion() (already bumped by
     * ProductObserver on every product create/update/delete) instead of a
     * dedicated forget*() method — the same driver-agnostic invalidation
     * SearchService's own product-count caches use, since a specific
     * $manufacturerIds combination has no single cache key to forget and the
     * default CACHE_STORE=file doesn't support Cache::tags().
     */
    public function rememberBrandProductCounts(array $manufacturerIds, callable $callback): mixed
    {
        if (! settings('performance.cache_manufacturers', true)) {
            return $callback();
        }

        $ttl = (int) settings('performance.cache_ttl_manufacturers', 60);
        sort($manufacturerIds);
        $key = 'brand_product_counts.v'.SearchService::cacheVersion().'.'.implode('_', $manufacturerIds);

        return Cache::remember($key, now()->addMinutes($ttl), $callback);
    }

    // ── Condition cache ───────────────────────────────────────────────────────

    /**
     * Remember the active condition list (New/Used/Refurbished, etc.) — a
     * tiny, rarely-changing reference table read on every search-results
     * page render (was a raw uncached query, duplicated in the controller
     * and again in the Blade view).
     */
    public function rememberActiveConditions(callable $callback): mixed
    {
        return Cache::remember('conditions.active', now()->addHour(), $callback);
    }

    /**
     * Remember EVERY condition (active or not) keyed by slug — backs
     * <x-ui.condition-badge>'s string-slug prop path (currently unused by
     * any live call site, which always passes an already-loaded Condition
     * model instead, but a per-call uncached lookup sitting in a shared UI
     * component is a landmine for whoever uses that prop shape next,
     * especially inside a @foreach over products). Deliberately unfiltered
     * by is_active (unlike rememberActiveConditions()) to match this
     * lookup's prior behavior — a product tagged with a since-deactivated
     * condition should still show its real badge, not silently fall back.
     */
    public function rememberConditionsBySlug(callable $callback): mixed
    {
        return Cache::remember('conditions.by_slug', now()->addHour(), $callback);
    }

    /**
     * Invalidate the active condition list cache.
     */
    public function forgetActiveConditions(): void
    {
        Cache::forget('conditions.active');
    }

    /**
     * Invalidate the by-slug condition map.
     */
    public function forgetConditionsBySlug(): void
    {
        Cache::forget('conditions.by_slug');
    }

    // ── Homepage content-block cache ─────────────────────────────────────────
    // (testimonials/faqs/blog preview — the content rendered inside homepage
    // sections, previously hit live on every render despite the section list
    // itself already being cached above. Same on/off + TTL knob as sections —
    // this content lives inside a section, not a separate configurable thing.)

    public function rememberTestimonials(callable $callback): mixed
    {
        return $this->rememberHomeContent('home.testimonials', $callback);
    }

    public function forgetTestimonials(): void
    {
        Cache::forget('home.testimonials');
    }

    public function rememberFaqs(callable $callback): mixed
    {
        return $this->rememberHomeContent('home.faqs', $callback);
    }

    public function forgetFaqs(): void
    {
        Cache::forget('home.faqs');
    }

    public function rememberHomeBlogPosts(callable $callback): mixed
    {
        return $this->rememberHomeContent('home.blog_posts', $callback);
    }

    public function forgetHomeBlogPosts(): void
    {
        Cache::forget('home.blog_posts');
    }

    // ── Blog listing page cache ──────────────────────────────────────────────
    // (/{lang}/blog — NOT the paginated/filtered post query itself, which is
    // genuinely fresh-per-request "search" work like SearchService's own
    // results; these three run identically on EVERY visit regardless of
    // category/tag/search filters, so were pure redundant cost.)

    /**
     * Remember the "Featured" post (newest published post with an image) —
     * ran unconditionally on every single /blog visit.
     */
    public function rememberBlogFeaturedPost(callable $callback): mixed
    {
        return $this->rememberHomeContent('blog.featured_post', $callback);
    }

    public function forgetBlogFeaturedPost(): void
    {
        Cache::forget('blog.featured_post');
    }

    /**
     * Remember the sidebar category/tag filter lists (with published-post
     * counts) — also ran unconditionally on every /blog visit.
     */
    public function rememberBlogCategories(callable $callback): mixed
    {
        return $this->rememberHomeContent('blog.categories', $callback);
    }

    public function rememberBlogTags(callable $callback): mixed
    {
        return $this->rememberHomeContent('blog.tags', $callback);
    }

    public function forgetBlogFilters(): void
    {
        Cache::forget('blog.categories');
        Cache::forget('blog.tags');
    }

    // ── Homepage Page-override cache ─────────────────────────────────────────

    /**
     * Remember the "Set as Homepage" Page lookup — ran unconditionally at
     * the very top of every single homepage request, before the (already
     * cached) section-based path is even reached. Bound by the same TTL as
     * every other section-family cache, so a scheduled future publish takes
     * up to that long to actually flip the homepage over — same tradeoff
     * this codebase already accepts for scheduled blog posts.
     *
     * $callback must return a nullable Page wrapped in an array
     * (`['page' => $pageOrNull]`), never the bare nullable value — Laravel's
     * Cache::remember() re-invokes its callback on every call when the
     * stored value reads back as literal null (it can't tell "cached null on
     * purpose" from "nothing cached yet"), which would make this a permanent
     * no-op for the common case (no page is flagged as the homepage).
     */
    public function rememberHomepagePageOverride(callable $callback): mixed
    {
        return $this->rememberHomeContent('page.homepage_override', $callback)['page'];
    }

    public function forgetHomepagePageOverride(): void
    {
        Cache::forget('page.homepage_override');
    }

    private function rememberHomeContent(string $key, callable $callback): mixed
    {
        if (! settings('performance.cache_sections', true)) {
            return $callback();
        }

        $ttl = (int) settings('performance.cache_ttl_sections', 60);

        return Cache::remember($key, now()->addMinutes($ttl), $callback);
    }

    // ── Coupon cache ──────────────────────────────────────────────────────────

    /**
     * Remember a coupon lookup by code — hit on every cart/checkout
     * coupon-apply request. Invalidated by CouponObserver on write (rule #6);
     * the short TTL is a belt-and-suspenders freshness bound in case a coupon
     * is ever edited outside Eloquent.
     */
    public function rememberCouponByCode(string $code, callable $callback): mixed
    {
        return Cache::remember("coupon.code.{$code}", now()->addMinutes(15), $callback);
    }

    // ── Search Console stats cache ───────────────────────────────────────────

    /**
     * Remember the Search Console landing page's status-panel counts (active
     * brands/products) — was a bare Cache::remember() with no matching
     * forget() anywhere, so an admin deactivating a product or manufacturer
     * left this stale for up to search.cache_ttl_hours (default 6h). Now
     * invalidated by ProductObserver and ManufacturerObserver on every write.
     */
    public function rememberSearchConsoleStats(callable $callback): mixed
    {
        $ttl = (int) settings('search.cache_ttl_hours', 6);

        return Cache::remember('search_console_stats', now()->addHours($ttl), $callback);
    }

    public function forgetSearchConsoleStats(): void
    {
        Cache::forget('search_console_stats');
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

    // ── Bulk key-scoped purge (NEVER Cache::flush() — rule #5) ───────────────

    /**
     * Delete every application cache key by scanning the store's own key
     * prefix (Redis) and forgetting each one individually — the targeted
     * alternative to Artisan's `cache:clear`, which flushes the WHOLE cache
     * store (sessions included, if they share the same Redis connection).
     * Used by both the Cache Dashboard's manual purge and the Health Check
     * "Clear Cache" remediation action.
     *
     * Two bugs fixed here (found while building the Cache Dashboard rework,
     * both confirmed live against the real dev Redis):
     * 1. `Cache::getStore()->getRedis()` returns the Redis *factory*, and
     *    calling ->keys() on it resolves the DEFAULT connection (DB 0, used
     *    for queue/session) via magic __call — not the 'cache' connection
     *    (DB 1) where the app's actual cache lives. Now explicit.
     * 2. The old code stripped only the client-level prefix
     *    (database.redis.options.prefix) before calling Cache::forget(),
     *    leaving the cache-layer prefix (cache.prefix) still attached —
     *    Cache::forget() then re-applies cache.prefix on top of that,
     *    double-prefixing the lookup and silently failing to actually clear
     *    the real key. Now strips the FULL raw prefix (both layers) so
     *    Cache::forget() receives the true logical key.
     *
     * Confirmed live against the real dev Redis: KEYS and SCAN handle
     * client-level prefixing differently. KEYS auto-applies phpredis's
     * OPT_PREFIX to its pattern (so `KEYS 'oeparts_*'` and `KEYS '*'` return
     * identical results here) — the pattern must use ONLY cache.prefix, the
     * SAME rule as TTL/GET. This is the opposite of SCAN's match pattern,
     * which needs the full two-layer prefix (see CacheMetricsService, which
     * uses SCAN and therefore a different formula — do not copy this
     * pattern there). Returned keys from KEYS are NOT auto-stripped either
     * way, so the strip below still needs the full raw prefix.
     *
     * @return int number of keys cleared, or -1 if the driver isn't Redis
     */
    public function purgeAllApplicationCacheKeys(): int
    {
        try {
            $redis = Redis::connection('cache');
            $cachePrefix = (string) config('cache.prefix', '');
            $fullPrefix = (string) config('database.redis.options.prefix', 'laravel_database_') . $cachePrefix;
        } catch (\Exception $e) {
            return -1; // driver isn't Redis — no safe bulk-scan equivalent exists
        }

        $keys = $redis->keys($cachePrefix.'*');

        foreach ($keys as $key) {
            Cache::forget(Str::after($key, $fullPrefix));
        }

        return count($keys);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function sectionKey(string $location): string
    {
        return "sections.{$location}";
    }
}
