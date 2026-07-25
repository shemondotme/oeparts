<?php

namespace Tests\Unit;

use App\Enums\AdminNotificationCategory;
use App\Models\CacheMetricSnapshot;
use App\Services\AdminNotificationService;
use App\Services\CacheMetricsService;
use App\Services\CacheService;
use App\Services\SectionRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers CacheMetricsService::snapshot() — the throttled entry point that
 * persists cache-metric history and fires an ok->below-threshold hit-rate
 * alert, mirroring HealthCheckServiceSnapshotTest's shape exactly.
 *
 * serverHealth() is mocked (via a partial mock of CacheMetricsService
 * itself) rather than exercised for real, since the real dev Redis hit rate
 * can't be forced to a specific value from a test — only the transition
 * logic built on top of it is under test here.
 */
class CacheSnapshotAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::forget('cache_metrics:snapshot_lock');

        parent::tearDown();
    }

    private function healthWith(float $hitRate): array
    {
        return [
            'redis_version' => '7.0.0',
            'hit_rate' => $hitRate,
            'hits' => 10,
            'misses' => 5,
            'memory_used_bytes' => 1000,
            'memory_used_human' => '1000B',
            'memory_max_bytes' => 0,
            'memory_peak_human' => '1000B',
            'memory_used_pct' => null,
            'fragmentation_ratio' => 1.1,
            'rdb_last_save_at' => null,
            'rdb_last_bgsave_ok' => true,
            'aof_enabled' => false,
            'ops_per_sec' => 5,
            'evicted_keys' => 0,
            'maxmemory_policy' => 'noeviction',
            'total_keys' => 10,
            'connected_clients' => 1,
            'uptime_seconds' => 100,
        ];
    }

    private function partialService(float $hitRate): CacheMetricsService
    {
        $service = Mockery::mock(
            CacheMetricsService::class,
            [app(CacheService::class), app(SectionRendererService::class)]
        )->makePartial();

        $service->shouldReceive('serverHealth')->andReturn($this->healthWith($hitRate));

        return $service;
    }

    #[Test]
    public function first_snapshot_writes_one_row(): void
    {
        Cache::forget('cache_metrics:snapshot_lock');

        $this->partialService(90.0)->snapshot();

        $this->assertSame(1, CacheMetricSnapshot::count());
    }

    #[Test]
    public function a_second_snapshot_inside_the_throttle_window_writes_nothing_new(): void
    {
        Cache::forget('cache_metrics:snapshot_lock');

        $this->partialService(90.0)->snapshot();
        $countAfterFirst = CacheMetricSnapshot::count();

        $this->partialService(90.0)->snapshot();

        $this->assertSame($countAfterFirst, CacheMetricSnapshot::count());
    }

    #[Test]
    public function an_ok_to_below_threshold_transition_notifies_once(): void
    {
        Cache::forget('cache_metrics:snapshot_lock');

        CacheMetricSnapshot::create([
            'hit_rate' => 90,
            'memory_used_bytes' => 1000,
            'memory_max_bytes' => null,
            'fragmentation_ratio' => 1.1,
            'evicted_keys' => 0,
            'ops_per_sec' => 5,
            'total_keys' => 10,
            'recorded_at' => now()->subMinutes(5),
        ]);

        $mock = Mockery::mock(AdminNotificationService::class);
        $mock->shouldReceive('createForAll')
            ->once()
            ->with(AdminNotificationCategory::System, Mockery::type('string'), Mockery::type('string'), Mockery::any());
        $this->app->instance(AdminNotificationService::class, $mock);

        // default threshold is 50 (dashboard.cache_hit_rate_alert_threshold)
        $this->partialService(20.0)->snapshot();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function a_repeat_non_transitioning_low_reading_does_not_notify_again(): void
    {
        Cache::forget('cache_metrics:snapshot_lock');

        CacheMetricSnapshot::create([
            'hit_rate' => 20,
            'memory_used_bytes' => 1000,
            'memory_max_bytes' => null,
            'fragmentation_ratio' => 1.1,
            'evicted_keys' => 0,
            'ops_per_sec' => 5,
            'total_keys' => 10,
            'recorded_at' => now()->subMinutes(5),
        ]);

        $mock = Mockery::mock(AdminNotificationService::class);
        $mock->shouldNotReceive('createForAll');
        $this->app->instance(AdminNotificationService::class, $mock);

        $this->partialService(15.0)->snapshot();

        $this->addToAssertionCount(1);
    }
}
