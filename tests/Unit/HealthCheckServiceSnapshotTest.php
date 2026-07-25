<?php

namespace Tests\Unit;

use App\Enums\AdminNotificationCategory;
use App\Models\HealthCheckSnapshot;
use App\Services\AdminNotificationService;
use App\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers HealthCheckService::snapshot() — the single gated entry point that
 * persists health-check history and fires ok->non-ok transition alerts (see
 * the Health Check module rework: previously every check was a stateless
 * live snapshot with no history and no failure alerting at all).
 *
 * Transition scenarios drive the 'scheduler' check via the scheduler_heartbeat
 * cache key (the same lever HealthCheckServiceSchedulerTest uses) rather than
 * touching any real file/service, so these tests can't affect anything the
 * running app depends on.
 *
 * AdminNotificationService is mocked rather than exercised for real:
 * AdminNotificationService::batchCheck() runs a raw JSON_EXTRACT/JSON_UNQUOTE
 * query that is MySQL-specific and unsupported by the sqlite :memory: test
 * database — a pre-existing gap in that shared service, unrelated to this
 * change, so this test isolates snapshot()'s own transition-detection logic
 * instead of exercising that code path.
 */
class HealthCheckServiceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::forget('health_check:snapshot_lock');
        Cache::forget('scheduler_heartbeat');

        parent::tearDown();
    }

    #[Test]
    public function run_all_includes_a_structured_backup_key(): void
    {
        $result = (new HealthCheckService)->runAll();

        $this->assertArrayHasKey('backup', $result['checks']);
        $this->assertArrayHasKey('status', $result['checks']['backup']);
        $this->assertArrayHasKey('detail', $result['checks']['backup']);
        $this->assertArrayHasKey('response_time_ms', $result['checks']['backup']);
    }

    #[Test]
    public function first_snapshot_writes_one_row_per_check(): void
    {
        Cache::forget('health_check:snapshot_lock');

        $result = (new HealthCheckService)->snapshot();

        $this->assertSame(
            count($result['checks']),
            HealthCheckSnapshot::count(),
        );
    }

    #[Test]
    public function a_second_snapshot_inside_the_throttle_window_writes_nothing_new(): void
    {
        Cache::forget('health_check:snapshot_lock');

        (new HealthCheckService)->snapshot();
        $countAfterFirst = HealthCheckSnapshot::count();

        (new HealthCheckService)->snapshot();

        $this->assertSame($countAfterFirst, HealthCheckSnapshot::count());
    }

    #[Test]
    public function an_ok_to_stale_transition_notifies_once_via_admin_notification_service(): void
    {
        Cache::forget('health_check:snapshot_lock');

        HealthCheckSnapshot::create([
            'check_key'        => 'scheduler',
            'status'           => 'ok',
            'detail'           => 'beat 5s ago',
            'response_time_ms' => null,
            'checked_at'       => now()->subMinutes(5),
        ]);

        // 10 minutes old is 'stale' under the default 3-minute threshold, so
        // this snapshot() call sees an ok -> stale transition for 'scheduler'.
        Cache::put('scheduler_heartbeat', now()->subMinutes(10)->toIso8601String(), 900);

        $mock = Mockery::mock(AdminNotificationService::class);
        $mock->shouldReceive('createForAll')
            ->once()
            ->with(AdminNotificationCategory::System, Mockery::type('string'), Mockery::type('string'), Mockery::any());
        $this->app->instance(AdminNotificationService::class, $mock);

        (new HealthCheckService)->snapshot();

        // Mockery's shouldReceive(...)->once() is verified automatically on
        // container teardown (Mockery::close(), wired into TestCase); no
        // extra assertion needed here.
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function a_repeat_non_transitioning_snapshot_does_not_notify_again(): void
    {
        Cache::forget('health_check:snapshot_lock');

        HealthCheckSnapshot::create([
            'check_key'        => 'scheduler',
            'status'           => 'stale',
            'detail'           => '600s since last beat',
            'response_time_ms' => null,
            'checked_at'       => now()->subMinutes(5),
        ]);

        Cache::put('scheduler_heartbeat', now()->subMinutes(10)->toIso8601String(), 900);

        $mock = Mockery::mock(AdminNotificationService::class);
        $mock->shouldNotReceive('createForAll');
        $this->app->instance(AdminNotificationService::class, $mock);

        (new HealthCheckService)->snapshot();

        $this->addToAssertionCount(1);
    }
}
