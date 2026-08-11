<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Services\CacheMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CacheMetricsService is a thin introspection layer over the REAL Redis
 * 'cache' connection (independent of CACHE_STORE=array set for everything
 * else in phpunit.xml — Redis::connection('cache') always resolves via
 * REDIS_HOST regardless of that setting). Tests that need seeded keys write
 * them under a dedicated 'oe_test_scan.' prefix (via the Cache facade, so
 * they get the same real prefixing scanKeys() has to unwind) and clean up
 * explicitly in tearDown() — this can never pollute app-logical cache keys.
 *
 * scanKeys() finding the seeded keys is the actual proof the null-cursor
 * SCAN fix works: a naive cursor-starts-at-0 implementation returns false
 * on its very first call regardless of pattern (confirmed live against this
 * project's dev Redis while building this service) and so would report zero
 * matches even with real keys present in the store.
 *
 * phpunit.xml pins CACHE_STORE=array for every other test in the suite (a
 * deliberate isolation fix — see TestCase::setUp()'s array-store flush), so
 * the Cache facade normally never touches Redis here. CacheMetricsService
 * itself always bypasses that and reads Redis::connection('cache') directly
 * regardless of config('cache.default') — but production's warm/clear
 * callables (SectionRendererService, CacheService::forget*()) go through
 * the Cache facade, so for THIS class only, cache.default is flipped to
 * 'redis' (config/cache.php's 'redis' store already resolves to the same
 * 'cache' connection) so seeded/warmed keys and CacheMetricsService's reads
 * agree on where data lives. Laravel boots a fresh Application per test
 * method, so this never leaks into other test classes.
 */
class CacheMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'redis']);
    }

    protected function tearDown(): void
    {
        Cache::forget('oe_test_scan.alpha');
        Cache::forget('oe_test_scan.beta');
        Cache::forget('manufacturers.active');
        Cache::forget('conditions.active');
        Cache::forget('search_console_stats');
        Cache::forget('manufacturers.active.all');
        Cache::forget('conditions.by_slug');
        Cache::forget('blog.featured_post');
        Cache::forget('blog.categories');
        Cache::forget('blog.tags');
        Cache::forget('page.homepage_override');

        parent::tearDown();
    }

    #[Test]
    public function server_health_returns_the_expected_keys_and_types(): void
    {
        $health = (new CacheMetricsService(app(\App\Services\CacheService::class), app(\App\Services\SectionRendererService::class)))->serverHealth();

        foreach (['redis_version', 'hit_rate', 'hits', 'misses', 'memory_used_bytes', 'memory_used_human', 'total_keys', 'connected_clients', 'uptime_seconds'] as $key) {
            $this->assertArrayHasKey($key, $health);
        }

        $this->assertIsFloat($health['hit_rate']);
        $this->assertIsInt($health['total_keys']);
        $this->assertIsInt($health['uptime_seconds']);
    }

    #[Test]
    public function scan_keys_finds_seeded_keys_matching_the_pattern(): void
    {
        Cache::put('oe_test_scan.alpha', 'one', now()->addMinute());
        Cache::put('oe_test_scan.beta', 'two', now()->addMinute());

        $results = $this->service()->scanKeys('oe_test_scan.*');
        $foundKeys = collect($results)->pluck('key')->all();

        $this->assertContains('oe_test_scan.alpha', $foundKeys);
        $this->assertContains('oe_test_scan.beta', $foundKeys);

        foreach ($results as $result) {
            if (in_array($result['key'], ['oe_test_scan.alpha', 'oe_test_scan.beta'], true)) {
                $this->assertGreaterThan(0, $result['ttl']);
            }
        }
    }

    #[Test]
    public function scan_keys_returns_an_empty_array_when_nothing_matches(): void
    {
        $results = $this->service()->scanKeys('oe_test_definitely_nonexistent_pattern.*');

        $this->assertSame([], $results);
    }

    #[Test]
    public function category_breakdown_counts_a_seeded_manufacturers_key(): void
    {
        Cache::put('manufacturers.active', ['acme'], now()->addMinute());

        $rows = $this->service()->categoryBreakdown();
        $manufacturers = collect($rows)->firstWhere('key', 'manufacturers');

        $this->assertNotNull($manufacturers);
        $this->assertGreaterThanOrEqual(1, $manufacturers['keyCount']);
    }

    #[Test]
    public function warm_and_clear_category_round_trip_and_log_activity(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);

        $warmedCount = $this->service()->warmCategory('conditions', $admin->id);
        $this->assertSame(1, $warmedCount);
        $this->assertNotEmpty($this->service()->scanKeys('conditions.active'));
        $this->assertDatabaseHas('activity_logs', [
            'admin_id' => $admin->id,
            'action' => 'cache_category_warmed',
            'model_type' => 'conditions',
        ]);

        $clearedCount = $this->service()->clearCategory('conditions', $admin->id);
        $this->assertSame(1, $clearedCount);
        $this->assertSame([], $this->service()->scanKeys('conditions.active'));
        $this->assertDatabaseHas('activity_logs', [
            'admin_id' => $admin->id,
            'action' => 'cache_category_cleared',
            'model_type' => 'conditions',
        ]);
    }

    /**
     * Added after the ~100k-scale performance pass — five new caches
     * (search_console_stats, brands_listing, conditions_by_slug,
     * blog_listing, homepage_override) previously had no entry in
     * categoryDefinitions() at all, meaning no manual warm/clear path
     * existed anywhere in the admin panel for them.
     */
    #[Test]
    public function warm_and_clear_round_trips_for_every_new_category_added_this_pass(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);

        foreach (['search_console_stats', 'brands_listing', 'conditions_by_slug', 'blog_listing', 'homepage_override'] as $key) {
            $warmedCount = $this->service()->warmCategory($key, $admin->id);
            $this->assertGreaterThan(0, $warmedCount, "{$key} should warm at least one key");

            $clearedCount = $this->service()->clearCategory($key, $admin->id);
            $this->assertGreaterThan(0, $clearedCount, "{$key} should clear at least one key");
        }
    }

    #[Test]
    public function delete_key_removes_a_seeded_key(): void
    {
        Cache::put('oe_test_scan.alpha', 'one', now()->addMinute());

        $this->service()->deleteKey('oe_test_scan.alpha');

        $this->assertNull(Cache::get('oe_test_scan.alpha'));
    }

    private function service(): CacheMetricsService
    {
        return app(CacheMetricsService::class);
    }
}
