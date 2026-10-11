<?php

namespace Tests\Unit;

use App\Enums\AdminNotificationCategory;
use App\Models\HealthCheckSnapshot;
use App\Services\AdminNotificationService;
use App\Services\Cleanup\DiskUsageService;
use App\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Health Check "disk" check: warns before the drive fills up and points the admin at the Cleanup page.
 */
class HealthCheckDiskTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::forget('health_check:snapshot_lock');
        parent::tearDown();
    }

    private function diskAt(float $usedPercent): void
    {
        $this->app->instance(DiskUsageService::class, new DiskUsageService('/x', fn () => 1000.0, fn () => 1000.0 - $usedPercent * 10));
    }

    #[Test]
    public function the_disk_check_reports_ok_warn_and_fail_by_how_full_the_drive_is(): void
    {
        $this->diskAt(50);
        $ok = (new HealthCheckService)->checkDisk();
        $this->assertSame('ok', $ok['status']);
        $this->assertStringContainsString('50% used', $ok['detail']);

        $this->diskAt(85);
        $this->assertSame('warn', (new HealthCheckService)->checkDisk()['status']);

        $this->diskAt(97);
        $this->assertSame('fail', (new HealthCheckService)->checkDisk()['status']);
    }

    #[Test]
    public function the_threshold_follows_the_cleanup_setting(): void
    {
        config(['cleanup.disk_alert_pct' => 60]);
        $this->diskAt(65);

        $this->assertSame('warn', (new HealthCheckService)->checkDisk()['status']);
    }

    #[Test]
    public function an_unreadable_disk_is_unknown_never_a_false_alarm(): void
    {
        $this->app->instance(DiskUsageService::class, new DiskUsageService('/x', fn () => false, fn () => false));

        $check = (new HealthCheckService)->checkDisk();

        $this->assertSame('unknown', $check['status']);
    }

    #[Test]
    public function run_all_includes_disk_but_only_a_failing_disk_makes_the_system_degraded(): void
    {
        $this->diskAt(85); // warn
        $warn = (new HealthCheckService)->runAll();
        $this->assertArrayHasKey('disk', $warn['checks']);
        $this->assertNotSame('degraded', $warn['status'], 'filling up is a warning, not an outage');

        $this->diskAt(97); // fail
        $this->assertSame('degraded', (new HealthCheckService)->runAll()['status']);
    }

    #[Test]
    public function crossing_the_threshold_alerts_every_admin_once_and_links_to_the_cleanup_page(): void
    {
        Cache::forget('health_check:snapshot_lock');
        HealthCheckSnapshot::create(['check_key' => 'disk', 'status' => 'ok', 'detail' => '50% used', 'checked_at' => now()->subMinutes(5)]);
        $this->diskAt(85);

        $mock = Mockery::mock(AdminNotificationService::class);
        $mock->shouldReceive('createForAll')->once()->withArgs(function ($category, $title, $detail, $url, $extra) {
            return $category === AdminNotificationCategory::System
                && str_contains($title, 'Disk space')
                && is_string($url) && str_contains($url, 'cleanup-dashboard')
                && $extra === ['push_topic' => 'health_alert'];
        });
        $this->app->instance(AdminNotificationService::class, $mock);

        (new HealthCheckService)->snapshot();

        $this->addToAssertionCount(1);
    }
}
