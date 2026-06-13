<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\AdminCacheService;
use App\Services\WidgetPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the AdminCacheService bypass flag that lets tests exercise
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
        AdminCacheService::$bypassArrayDriverCheck = false;
        parent::tearDown();
    }

    // ── Default behavior (bypass = false, array driver) ─────────────────────

    #[Test]
    public function cache_is_skipped_on_array_driver_by_default(): void
    {
        $this->assertSame('array', config('cache.default'));
        $this->assertFalse(AdminCacheService::$bypassArrayDriverCheck);

        $calls = 0;
        AdminCacheService::dashboard('test:skip', function () use (&$calls) {
            $calls++;

            return ['hit' => false];
        });

        AdminCacheService::dashboard('test:skip', function () use (&$calls) {
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
        AdminCacheService::$bypassArrayDriverCheck = true;

        AdminCacheService::dashboard('test:store', fn () => ['value' => 42]);

        $this->assertTrue(
            Cache::has('admin:dashboard:test:store'),
            'Cache key must exist after first call with bypass enabled',
        );
    }

    #[Test]
    public function second_call_hits_cache_and_does_not_invoke_callback(): void
    {
        AdminCacheService::$bypassArrayDriverCheck = true;

        $calls = 0;

        AdminCacheService::dashboard('test:hit', function () use (&$calls) {
            $calls++;

            return ['result' => 'first'];
        }, 60);

        AdminCacheService::dashboard('test:hit', function () use (&$calls) {
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
        AdminCacheService::$bypassArrayDriverCheck = true;

        AdminCacheService::dashboard('kpi_stats:p30', fn () => ['period' => '30']);
        AdminCacheService::dashboard('kpi_stats:p7', fn () => ['period' => '7']);

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
        AdminCacheService::$bypassArrayDriverCheck = true;

        AdminCacheService::dashboard('test:forget', fn () => ['stored' => true]);
        $this->assertTrue(Cache::has('admin:dashboard:test:forget'));

        AdminCacheService::forget('test:forget');
        $this->assertFalse(Cache::has('admin:dashboard:test:forget'));
    }

    #[Test]
    public function after_forget_the_callback_runs_again(): void
    {
        AdminCacheService::$bypassArrayDriverCheck = true;

        $calls = 0;
        $closure = function () use (&$calls) {
            $calls++;

            return ['calls' => $calls];
        };

        AdminCacheService::dashboard('test:refill', $closure, 60);
        AdminCacheService::forget('test:refill');
        AdminCacheService::dashboard('test:refill', $closure, 60);

        $this->assertSame(2, $calls, 'Callback must run again after the key is forgotten');
    }

    #[Test]
    public function health_cache_uses_separate_namespace(): void
    {
        AdminCacheService::$bypassArrayDriverCheck = true;

        AdminCacheService::dashboard('health_strip:p-', fn () => ['type' => 'dashboard']);
        AdminCacheService::health('health_strip:checks', fn () => ['type' => 'health']);

        $this->assertTrue(Cache::has('admin:dashboard:health_strip:p-'));
        $this->assertTrue(Cache::has('admin:health:health_strip:checks'));

        $dashboardVal = Cache::get('admin:dashboard:health_strip:p-');
        $healthVal = Cache::get('admin:health:health_strip:checks');

        $this->assertNotSame($dashboardVal, $healthVal);
    }

    #[Test]
    public function forget_clears_both_dashboard_and_health_namespaces(): void
    {
        AdminCacheService::$bypassArrayDriverCheck = true;

        AdminCacheService::dashboard('disk_space:p-', fn () => ['disk' => 'dashboard']);
        AdminCacheService::health('disk_space:disk', fn () => ['disk' => 'health']);

        AdminCacheService::forget('disk_space:p-');
        AdminCacheService::forget('disk_space:disk');

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
}
