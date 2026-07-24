<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\AdminWidgetCacheService;
use App\Services\WidgetPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the AdminWidgetCacheService bypass flag that lets tests exercise
 * cache remember/forget paths on the array driver.
 *
 * IMPORTANT: every test that sets $bypassArrayDriverCheck = true MUST reset
 * it in tearDown (handled here centrally) so other test files are unaffected.
 */
class DashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);

        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    protected function tearDown(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = false;
        parent::tearDown();
    }

    // ── Default behavior (bypass = false, array driver) ─────────────────────

    #[Test]
    public function cache_is_skipped_on_array_driver_by_default(): void
    {
        $this->assertSame('array', config('cache.default'));
        $this->assertFalse(AdminWidgetCacheService::$bypassArrayDriverCheck);

        $calls = 0;
        AdminWidgetCacheService::dashboard('test:skip', function () use (&$calls) {
            $calls++;

            return ['hit' => false];
        });

        AdminWidgetCacheService::dashboard('test:skip', function () use (&$calls) {
            $calls++;

            return ['hit' => false];
        });

        // Callback runs on every call — cache is not used
        $this->assertSame(2, $calls, 'Without bypass, cache must be skipped on array driver');
        $this->assertFalse(Cache::has('admin:dashboard:test:skip'));
    }

    // ── Bypass enabled ───────────────────────────────────────────────────────

    #[Test]
    public function bypass_flag_allows_cache_to_store_and_return_data(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        AdminWidgetCacheService::dashboard('test:store', fn () => ['value' => 42]);

        $this->assertTrue(
            Cache::has('admin:dashboard:test:store'),
            'Cache key must exist after first call with bypass enabled',
        );
    }

    #[Test]
    public function second_call_hits_cache_and_does_not_invoke_callback(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        $calls = 0;

        AdminWidgetCacheService::dashboard('test:hit', function () use (&$calls) {
            $calls++;

            return ['result' => 'first'];
        }, 60);

        AdminWidgetCacheService::dashboard('test:hit', function () use (&$calls) {
            $calls++;

            return ['result' => 'second'];
        }, 60);

        $this->assertSame(1, $calls, 'Callback must run exactly once — second call is a cache hit');

        $cached = Cache::get('admin:dashboard:test:hit');
        $this->assertSame(['result' => 'first'], $cached);
    }

    #[Test]
    public function different_periods_produce_different_cache_keys(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        AdminWidgetCacheService::dashboard('kpi_stats:p30', fn () => ['period' => '30']);
        AdminWidgetCacheService::dashboard('kpi_stats:p7', fn () => ['period' => '7']);

        $this->assertTrue(Cache::has('admin:dashboard:kpi_stats:p30'));
        $this->assertTrue(Cache::has('admin:dashboard:kpi_stats:p7'));

        $this->assertNotSame(
            Cache::get('admin:dashboard:kpi_stats:p30'),
            Cache::get('admin:dashboard:kpi_stats:p7'),
        );
    }

    #[Test]
    public function forget_removes_the_cache_entry(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        AdminWidgetCacheService::dashboard('test:forget', fn () => ['stored' => true]);
        $this->assertTrue(Cache::has('admin:dashboard:test:forget'));

        AdminWidgetCacheService::forget('test:forget');
        $this->assertFalse(Cache::has('admin:dashboard:test:forget'));
    }

    #[Test]
    public function after_forget_the_callback_runs_again(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        $calls = 0;
        $closure = function () use (&$calls) {
            $calls++;

            return ['calls' => $calls];
        };

        AdminWidgetCacheService::dashboard('test:refill', $closure, 60);
        AdminWidgetCacheService::forget('test:refill');
        AdminWidgetCacheService::dashboard('test:refill', $closure, 60);

        $this->assertSame(2, $calls, 'Callback must run again after the key is forgotten');
    }

    #[Test]
    public function health_cache_uses_separate_namespace(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        AdminWidgetCacheService::dashboard('health_strip:p-', fn () => ['type' => 'dashboard']);
        AdminWidgetCacheService::health('health_strip:checks', fn () => ['type' => 'health']);

        $this->assertTrue(Cache::has('admin:dashboard:health_strip:p-'));
        $this->assertTrue(Cache::has('admin:health:health_strip:checks'));

        $dashboardVal = Cache::get('admin:dashboard:health_strip:p-');
        $healthVal = Cache::get('admin:health:health_strip:checks');

        $this->assertNotSame($dashboardVal, $healthVal);
    }

    #[Test]
    public function forget_clears_both_dashboard_and_health_namespaces(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        AdminWidgetCacheService::dashboard('disk_space:p-', fn () => ['disk' => 'dashboard']);
        AdminWidgetCacheService::health('disk_space:disk', fn () => ['disk' => 'health']);

        AdminWidgetCacheService::forget('disk_space:p-');
        AdminWidgetCacheService::forget('disk_space:disk');

        $this->assertFalse(Cache::has('admin:dashboard:disk_space:p-'));
        $this->assertFalse(Cache::has('admin:health:disk_space:disk'));
    }

    // ── Widget id derivation ─────────────────────────────────────────────────

    #[Test]
    public function all_widget_ids_in_registry_are_resolvable_from_class(): void
    {
        $service = app(WidgetPreferenceService::class);

        foreach (WidgetPreferenceService::WIDGETS as $id => $config) {
            $resolved = $service->getWidgetId($config['class']);
            $this->assertSame($id, $resolved, "Widget class {$config['class']} must resolve back to id [{$id}]");
        }
    }

    #[Test]
    public function ttl_for_every_registry_widget_is_a_positive_integer(): void
    {
        foreach (WidgetPreferenceService::WIDGETS as $id => $config) {
            $ttl = WidgetPreferenceService::ttlFor($config['class']);
            $this->assertGreaterThan(0, $ttl, "Widget [{$id}] must have a positive TTL");
        }
    }

    // ── WidgetPreferenceService::forgetCache() — the performance-sweep fix ──
    // (observers previously forgot hand-typed keys like `admin:dashboard:kpi_stats`
    // that never matched the real, period-suffixed key — 100% dead invalidation)

    #[Test]
    public function forget_cache_clears_every_period_key_for_a_period_capable_widget(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        foreach (['1', '7', '30', '90', '365'] as $period) {
            AdminWidgetCacheService::dashboard("order_stats_overview:p{$period}", fn () => ['p' => $period]);
        }

        WidgetPreferenceService::forgetCache('order_stats_overview');

        foreach (['1', '7', '30', '90', '365'] as $period) {
            $this->assertFalse(
                Cache::has("admin:dashboard:order_stats_overview:p{$period}"),
                "Period {$period} cache must be cleared",
            );
        }
    }

    #[Test]
    public function forget_cache_clears_the_single_key_for_a_non_period_widget(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        AdminWidgetCacheService::dashboard('recent_orders:p-', fn () => ['orders' => []]);
        $this->assertTrue(Cache::has('admin:dashboard:recent_orders:p-'));

        WidgetPreferenceService::forgetCache('recent_orders');

        $this->assertFalse(Cache::has('admin:dashboard:recent_orders:p-'));
    }

    #[Test]
    public function forget_cache_is_a_noop_for_an_unknown_widget_id(): void
    {
        // Must never throw — observers call this from a CRUD hot path.
        WidgetPreferenceService::forgetCache('nonexistent_widget');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function product_observer_invalidates_stock_alert_and_catalog_widget_caches(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        AdminWidgetCacheService::dashboard('stock_alert:p-', fn () => ['stale' => true]);
        AdminWidgetCacheService::dashboard('manufacturing_stats:p30', fn () => ['stale' => true]);
        AdminWidgetCacheService::dashboard('new_products_added:p30', fn () => ['stale' => true]);

        \App\Models\Product::factory()->create();

        $this->assertFalse(Cache::has('admin:dashboard:stock_alert:p-'));
        $this->assertFalse(Cache::has('admin:dashboard:manufacturing_stats:p30'));
        $this->assertFalse(Cache::has('admin:dashboard:new_products_added:p30'));
    }

    #[Test]
    public function manufacturer_observer_invalidates_manufacturer_list_and_dashboard_caches(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        Cache::put('manufacturers.active', ['stale' => true], 3600);
        AdminWidgetCacheService::dashboard('manufacturer_revenue:p30', fn () => ['stale' => true]);
        AdminWidgetCacheService::dashboard('manufacturing_stats:p30', fn () => ['stale' => true]);

        $manufacturer = \App\Models\Manufacturer::factory()->create();
        $manufacturer->update(['is_active' => false]);

        $this->assertFalse(Cache::has('manufacturers.active'));
        $this->assertFalse(Cache::has('admin:dashboard:manufacturer_revenue:p30'));
        $this->assertFalse(Cache::has('admin:dashboard:manufacturing_stats:p30'));
    }
}
