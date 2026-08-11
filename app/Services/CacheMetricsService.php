<?php

namespace App\Services;

use App\Enums\AdminNotificationCategory;
use App\Enums\SectionLocation;
use App\Filament\Pages\System\CacheDashboard;
use App\Models\ActivityLog;
use App\Models\CacheMetricSnapshot;
use App\Models\Condition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * CacheMetricsService — introspects and manages the Redis cache STORE itself
 * (server health, per-category key breakdown, live key browsing, history).
 *
 * This is a different responsibility from CacheService, which owns the app's
 * own remember/forget key helpers — CacheService doesn't know how to read
 * Redis INFO or enumerate keys, and this service doesn't know how any
 * individual cache entry's data is computed (it delegates warming to
 * SectionRendererService's public wrappers, or CacheService's forget*()
 * methods for clearing).
 *
 * Powers App\Filament\Pages\System\CacheDashboard.
 */
class CacheMetricsService
{
    private const SNAPSHOT_THROTTLE_SECONDS = 60;

    public function __construct(
        private CacheService $cacheService,
        private SectionRendererService $sectionRenderer,
    ) {}

    /**
     * Redis INFO-derived server health, all from one call (confirmed: no
     * section argument needed — the default INFO output already includes
     * every field used here, across both phpredis and predis clients).
     *
     * @return array<string, mixed>
     */
    public function serverHealth(): array
    {
        $info = Redis::connection('cache')->info();

        $hits = (int) ($info['keyspace_hits'] ?? 0);
        $misses = (int) ($info['keyspace_misses'] ?? 0);
        $total = $hits + $misses;

        $memoryUsed = (int) ($info['used_memory'] ?? 0);
        $maxMemory = (int) ($info['maxmemory'] ?? 0);

        return [
            'redis_version'       => $info['redis_version'] ?? 'unknown',
            'hit_rate'            => $total > 0 ? round(($hits / $total) * 100, 1) : 0.0,
            'hits'                => $hits,
            'misses'              => $misses,
            'memory_used_bytes'   => $memoryUsed,
            'memory_used_human'   => $info['used_memory_human'] ?? 'N/A',
            'memory_max_bytes'    => $maxMemory,
            'memory_peak_human'   => $info['used_memory_peak_human'] ?? 'N/A',
            'memory_used_pct'     => $maxMemory > 0 ? round(($memoryUsed / $maxMemory) * 100, 1) : null,
            'fragmentation_ratio' => isset($info['mem_fragmentation_ratio']) ? (float) $info['mem_fragmentation_ratio'] : null,
            'rdb_last_save_at'    => isset($info['rdb_last_save_time']) ? (int) $info['rdb_last_save_time'] : null,
            'rdb_last_bgsave_ok'  => ($info['rdb_last_bgsave_status'] ?? 'ok') === 'ok',
            'aof_enabled'         => (bool) ($info['aof_enabled'] ?? false),
            'ops_per_sec'         => isset($info['instantaneous_ops_per_sec']) ? (int) $info['instantaneous_ops_per_sec'] : null,
            'evicted_keys'        => (int) ($info['evicted_keys'] ?? 0),
            'maxmemory_policy'    => $info['maxmemory_policy'] ?? 'noeviction',
            'total_keys'          => Redis::connection('cache')->dbSize(),
            'connected_clients'   => (int) ($info['connected_clients'] ?? 0),
            'uptime_seconds'      => (int) ($info['uptime_in_seconds'] ?? 0),
        ];
    }

    /**
     * Static definitions for the known cache categories: label, the logical
     * key pattern(s) used for SCAN counting, how to read its TTL, and its
     * warm/clear callables. `warm` is null for categories with no clean
     * "warm all" concept (coupons are per-code/dynamic, settings groups are
     * populated lazily by whatever settings() call happens next — confirmed
     * no real warm call site exists for either).
     *
     * @return array<string, array{label: string, sub: string, patterns: array<int, string>, ttlMinutes: callable(): int, warm: ?callable, clear: callable(): int}>
     */
    public function categoryDefinitions(): array
    {
        return [
            'sections' => [
                'label' => 'Sections',
                'sub' => 'homepage · landing',
                'patterns' => array_map(fn (SectionLocation $l) => "sections.{$l->value}", SectionLocation::cases()),
                'ttlMinutes' => fn () => (int) settings('performance.cache_ttl_sections', 60),
                'warm' => function (): int {
                    foreach (SectionLocation::cases() as $location) {
                        $this->sectionRenderer->getSections($location->value);
                    }
                    return count(SectionLocation::cases());
                },
                'clear' => function (): int {
                    foreach (SectionLocation::cases() as $location) {
                        $this->cacheService->forgetSections($location->value);
                    }
                    return count(SectionLocation::cases());
                },
            ],
            'homepage_content' => [
                'label' => 'Homepage Content',
                'sub' => 'testimonials · faqs · blog previews',
                'patterns' => ['home.testimonials', 'home.faqs', 'home.blog_posts'],
                'ttlMinutes' => fn () => (int) settings('performance.cache_ttl_sections', 60),
                'warm' => function (): int {
                    $this->sectionRenderer->warmTestimonials();
                    $this->sectionRenderer->warmFaqs();
                    $this->sectionRenderer->warmHomeBlogPosts();
                    return 3;
                },
                'clear' => function (): int {
                    $this->cacheService->forgetTestimonials();
                    $this->cacheService->forgetFaqs();
                    $this->cacheService->forgetHomeBlogPosts();
                    return 3;
                },
            ],
            'manufacturers' => [
                'label' => 'Manufacturers',
                'sub' => 'active manufacturer list',
                'patterns' => ['manufacturers.active'],
                'ttlMinutes' => fn () => (int) settings('performance.cache_ttl_manufacturers', 60),
                'warm' => function (): int {
                    $this->sectionRenderer->warmManufacturers();
                    return 1;
                },
                'clear' => function (): int {
                    $this->cacheService->forgetManufacturers();
                    return 1;
                },
            ],
            'conditions' => [
                'label' => 'Active Conditions',
                'sub' => 'New / Used / Refurbished',
                'patterns' => ['conditions.active'],
                'ttlMinutes' => fn () => 60,
                'warm' => function (): int {
                    $this->cacheService->rememberActiveConditions(
                        fn () => Condition::where('is_active', true)->orderBy('sort_order')->get()
                    );
                    return 1;
                },
                'clear' => function (): int {
                    $this->cacheService->forgetActiveConditions();
                    return 1;
                },
            ],
            'coupons' => [
                'label' => 'Coupon Lookups',
                'sub' => 'per-code, dynamic keys',
                'patterns' => ['coupon.code.*'],
                'ttlMinutes' => fn () => 15,
                'warm' => null,
                'clear' => function (): int {
                    return $this->clearByPattern('coupon.code.*');
                },
            ],
            'hero_stats' => [
                'label' => 'Hero Stats',
                'sub' => 'parts / manufacturers / cross-refs count',
                'patterns' => ['hero.stats'],
                'ttlMinutes' => fn () => 360,
                'warm' => function (): int {
                    $this->sectionRenderer->warmHeroStats();
                    return 1;
                },
                'clear' => function (): int {
                    $this->cacheService->forgetHeroStats();
                    return 1;
                },
            ],
            'popular_oems' => [
                'label' => 'Popular OEMs',
                'sub' => 'hero "INDEXED:" strip',
                'patterns' => ['hero.popular_oems'],
                'ttlMinutes' => fn () => 60,
                'warm' => function (): int {
                    $this->sectionRenderer->warmPopularOems();
                    return 1;
                },
                'clear' => function (): int {
                    $this->cacheService->forgetPopularOems();
                    return 1;
                },
            ],
            'settings_groups' => [
                'label' => 'Settings Groups',
                'sub' => 'general · seo · performance · and more',
                'patterns' => ['settings.*'],
                'ttlMinutes' => fn () => 5,
                'warm' => null,
                'clear' => function (): int {
                    return $this->clearByPattern('settings.*');
                },
            ],
            'search_console_stats' => [
                'label' => 'Search Console Stats',
                'sub' => 'active brand / product counts (/parts)',
                'patterns' => ['search_console_stats'],
                'ttlMinutes' => fn () => ((int) settings('search.cache_ttl_hours', 6)) * 60,
                'warm' => function (): int {
                    $this->cacheService->rememberSearchConsoleStats(fn () => [
                        'brands'   => \App\Models\Manufacturer::where('is_active', true)->count(),
                        'products' => \App\Models\Product::where('is_active', true)->count(),
                    ]);

                    return 1;
                },
                'clear' => function (): int {
                    $this->cacheService->forgetSearchConsoleStats();

                    return 1;
                },
            ],
            'brands_listing' => [
                'label' => 'Brands Directory',
                'sub' => 'every active manufacturer (/brands page)',
                'patterns' => ['manufacturers.active.all'],
                'ttlMinutes' => fn () => (int) settings('performance.cache_ttl_manufacturers', 60),
                'warm' => function (): int {
                    $this->cacheService->rememberAllActiveManufacturers(
                        fn () => \App\Models\Manufacturer::where('is_active', true)->with('logo')->get()
                    );

                    return 1;
                },
                'clear' => function (): int {
                    $this->cacheService->forgetManufacturers();

                    return 1;
                },
            ],
            'conditions_by_slug' => [
                'label' => 'Conditions (by slug)',
                'sub' => '<x-ui.condition-badge> string-prop lookups',
                'patterns' => ['conditions.by_slug'],
                'ttlMinutes' => fn () => 60,
                'warm' => function (): int {
                    $this->cacheService->rememberConditionsBySlug(fn () => Condition::all()->keyBy('slug'));

                    return 1;
                },
                'clear' => function (): int {
                    $this->cacheService->forgetConditionsBySlug();

                    return 1;
                },
            ],
            'blog_listing' => [
                'label' => 'Blog Listing',
                'sub' => 'featured post · category & tag counts (/blog)',
                'patterns' => ['blog.featured_post', 'blog.categories', 'blog.tags'],
                'ttlMinutes' => fn () => (int) settings('performance.cache_ttl_sections', 60),
                'warm' => function (): int {
                    $this->cacheService->rememberBlogFeaturedPost(
                        fn () => \App\Models\BlogPost::with('featuredImage')
                            ->published()
                            ->whereNotNull('featured_image_id')
                            ->orderBy('published_at', 'desc')
                            ->first()
                    );
                    $this->cacheService->rememberBlogCategories(
                        fn () => \App\Models\Category::whereHas('blogPosts', fn ($q) => $q->published())
                            ->withCount(['blogPosts as blog_posts_count' => fn ($q) => $q->published()])
                            ->get()
                    );
                    $this->cacheService->rememberBlogTags(
                        fn () => \App\Models\BlogTag::whereHas('posts', fn ($q) => $q->published())->get()
                    );

                    return 3;
                },
                'clear' => function (): int {
                    $this->cacheService->forgetBlogFeaturedPost();
                    $this->cacheService->forgetBlogFilters();

                    return 3;
                },
            ],
            'homepage_override' => [
                'label' => 'Homepage Page Override',
                'sub' => '"Set as Homepage" lookup',
                'patterns' => ['page.homepage_override'],
                'ttlMinutes' => fn () => (int) settings('performance.cache_ttl_sections', 60),
                'warm' => function (): int {
                    $this->cacheService->rememberHomepagePageOverride(fn () => ['page' => \App\Models\Page::with('featuredImage')
                        ->where('is_homepage', true)
                        ->where('status', \App\Enums\ContentStatus::Published)
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now())
                        ->first(),
                    ]);

                    return 1;
                },
                'clear' => function (): int {
                    $this->cacheService->forgetHomepagePageOverride();

                    return 1;
                },
            ],
        ];
    }

    /**
     * Names of categories that support a "Warm" action — used for "Warm All".
     *
     * @return array<int, string>
     */
    public function warmableCategoryKeys(): array
    {
        return array_keys(array_filter(
            $this->categoryDefinitions(),
            fn (array $def) => $def['warm'] !== null,
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categoryBreakdown(): array
    {
        $rows = [];

        foreach ($this->categoryDefinitions() as $key => $def) {
            $count = 0;
            foreach ($def['patterns'] as $pattern) {
                $count += count($this->scanAll($this->fullRawPrefix() . $pattern));
            }

            $rows[] = [
                'key' => $key,
                'label' => $def['label'],
                'sub' => $def['sub'],
                'keyCount' => $count,
                'ttlMinutes' => $def['ttlMinutes'](),
                'canWarm' => $def['warm'] !== null,
                'lastCleared' => $this->lastActivity('cache_category_cleared', $key),
                'lastWarmed' => $this->lastActivity('cache_category_warmed', $key),
            ];
        }

        return $rows;
    }

    /**
     * Key Browser: search live Redis keys by a logical pattern (e.g. 'manufacturers.*').
     *
     * @return array<int, array{key: string, ttl: int, sizeBytes: int|null}>
     */
    public function scanKeys(string $pattern, int $limit = 50): array
    {
        $pattern = trim($pattern) ?: '*';
        $rawKeys = $this->scanAll($this->fullRawPrefix() . $pattern);
        $rawKeys = array_slice(array_unique($rawKeys), 0, $limit);

        $results = [];
        foreach ($rawKeys as $rawKey) {
            $logicalKey = $this->stripFullRawPrefix($rawKey);

            $results[] = [
                'key' => $logicalKey,
                'ttl' => Redis::connection('cache')->ttl($this->cacheLayerPrefix() . $logicalKey),
                'sizeBytes' => $this->keySizeBytes($rawKey),
            ];
        }

        return $results;
    }

    public function deleteKey(string $logicalKey): void
    {
        Redis::connection('cache')->del($this->cacheLayerPrefix() . $logicalKey);
    }

    public function warmCategory(string $categoryKey, ?int $adminId = null): int
    {
        $def = $this->categoryDefinitions()[$categoryKey] ?? null;

        if (! $def || ! $def['warm']) {
            return 0;
        }

        $count = $def['warm']();
        $this->logActivity('cache_category_warmed', $categoryKey, $adminId);

        return $count;
    }

    public function clearCategory(string $categoryKey, ?int $adminId = null): int
    {
        $def = $this->categoryDefinitions()[$categoryKey] ?? null;

        if (! $def) {
            return 0;
        }

        $count = $def['clear']();
        $this->logActivity('cache_category_cleared', $categoryKey, $adminId);

        return $count;
    }

    /**
     * Throttled history-recording entry point (mirrors HealthCheckService::
     * snapshot() exactly): at most once per SNAPSHOT_THROTTLE_SECONDS across
     * every process, records one CacheMetricSnapshot row and fires a one-time
     * alert on an ok -> below-threshold hit-rate transition.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $health = $this->serverHealth();

        if (! Cache::add('cache_metrics:snapshot_lock', 1, self::SNAPSHOT_THROTTLE_SECONDS)) {
            return $health;
        }

        $previous = CacheMetricSnapshot::query()->latest('recorded_at')->latest('id')->first();

        CacheMetricSnapshot::create([
            'hit_rate' => (int) round($health['hit_rate']),
            'memory_used_bytes' => $health['memory_used_bytes'],
            'memory_max_bytes' => $health['memory_max_bytes'] ?: null,
            'fragmentation_ratio' => $health['fragmentation_ratio'],
            'evicted_keys' => $health['evicted_keys'],
            'ops_per_sec' => $health['ops_per_sec'],
            'total_keys' => $health['total_keys'],
            'recorded_at' => now(),
        ]);

        $threshold = (int) settings('dashboard.cache_hit_rate_alert_threshold', 50);
        $nowLow = $health['hit_rate'] < $threshold;
        $wasOk = $previous === null ? false : $previous->hit_rate >= $threshold;

        if ($nowLow && $wasOk) {
            app(AdminNotificationService::class)->createForAll(
                AdminNotificationCategory::System,
                'Cache hit rate dropped below threshold',
                "Hit rate is now {$health['hit_rate']}%, below the configured {$threshold}% threshold.",
                CacheDashboard::getUrl(),
            );
        }

        return $health;
    }

    /**
     * The Laravel Cache-facade prefix alone (config('cache.prefix')) — use
     * for TTL/GET/EXISTS/DEL on a specific logical key. phpredis's own
     * client-level OPT_PREFIX (database.redis.options.prefix) is applied
     * transparently by the client for these commands — confirmed live,
     * do NOT also prepend it here or the key won't be found.
     */
    private function cacheLayerPrefix(): string
    {
        return (string) config('cache.prefix');
    }

    /**
     * The full raw prefix (client-level + cache-layer, concatenated with no
     * separator, matching Illuminate\Cache\RedisStore's own key-building) —
     * use for SCAN match patterns, stripping SCAN results, and MEMORY USAGE
     * raw keys. Confirmed live: phpredis does NOT auto-adjust the SCAN match
     * pattern or auto-strip SCAN results the way it does for direct key
     * commands, so both prefixes must be included manually here.
     */
    private function fullRawPrefix(): string
    {
        return (string) config('database.redis.options.prefix') . (string) config('cache.prefix');
    }

    private function stripFullRawPrefix(string $rawKey): string
    {
        $prefix = $this->fullRawPrefix();

        return str_starts_with($rawKey, $prefix) ? substr($rawKey, strlen($prefix)) : $rawKey;
    }

    /**
     * The single shared, null-cursor-safe SCAN loop. Confirmed live: starting
     * the cursor at integer 0 makes Laravel's PhpRedisConnection::scan()
     * wrapper return false immediately on the very first call, regardless of
     * pattern — the cursor must start as null. Both categoryBreakdown() and
     * scanKeys() call this one method so the fix exists in exactly one place.
     *
     * @return array<int, string> raw (still fully prefixed) matching keys
     */
    private function scanAll(string $rawPattern): array
    {
        $keys = [];
        $cursor = null;

        do {
            $result = Redis::connection('cache')->scan($cursor, ['match' => $rawPattern, 'count' => 1000]);

            if ($result === false) {
                break;
            }

            [$cursor, $batch] = $result;
            $keys = array_merge($keys, $batch);
        } while ($cursor != 0);

        return $keys;
    }

    /**
     * Best-effort per-key size via MEMORY USAGE — not wrapped as a named
     * method by either Redis client, so it goes through executeRaw(), which
     * bypasses automatic prefixing entirely (confirmed live), hence the full
     * raw key built manually here. Never fatal: degrades to null.
     */
    private function keySizeBytes(string $rawKey): ?int
    {
        try {
            $size = Redis::connection('cache')->executeRaw(['MEMORY', 'USAGE', $rawKey]);

            return is_numeric($size) ? (int) $size : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Clear-by-pattern for the two dynamic-key categories (coupons, settings
     * groups) that don't have a single named CacheService::forget*() method —
     * scans for matching keys and deletes each individually (never a raw
     * FLUSHDB — this only ever targets the specific matched keys).
     */
    private function clearByPattern(string $logicalPattern): int
    {
        $rawKeys = $this->scanAll($this->fullRawPrefix() . $logicalPattern);

        foreach ($rawKeys as $rawKey) {
            Redis::connection('cache')->del($this->cacheLayerPrefix() . $this->stripFullRawPrefix($rawKey));
        }

        return count($rawKeys);
    }

    private function lastActivity(string $action, string $categoryKey): ?string
    {
        $log = ActivityLog::where('action', $action)
            ->where('model_type', $categoryKey)
            ->orderByDesc('created_at')
            ->first();

        return $log?->created_at?->diffForHumans();
    }

    private function logActivity(string $action, string $categoryKey, ?int $adminId): void
    {
        ActivityLog::create([
            'admin_id' => $adminId ?? auth('admin')->id(),
            'action' => $action,
            'model_type' => $categoryKey,
            'model_id' => null,
            'old_values' => [],
            'new_values' => [],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);
    }
}
