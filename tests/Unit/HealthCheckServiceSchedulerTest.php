<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\HealthCheckService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression test for a Carbon 3 signed-diff bug: HealthCheckService::
 * checkScheduler() used now()->diffInMinutes($heartbeat) without abs().
 * Carbon 2 always returned an absolute value there; Carbon 3 (this project's
 * version, confirmed via composer.lock: 3.13.0) returns a SIGNED value —
 * negative when the argument is in the past, which a cron heartbeat always
 * is. `$diff <= 3` was therefore always true (a negative number is always
 * <= 3), so this check could never report 'stale' no matter how long the
 * real system cron had actually been dead. Found while building the
 * HTTP-triggered cron fallback (App\Http\Middleware\TriggerDueScheduledTasks),
 * whose own staleness check had the identical bug — same fix (abs()) applied
 * in both places, plus App\Filament\Widgets\HealthStrip's checkQueue()/
 * checkScheduler().
 */
class HealthCheckServiceSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::forget('scheduler_heartbeat');
        Setting::where('group', 'dashboard')->where('key', 'scheduler_stale_minutes')->delete();
        app(SettingsService::class)->forget('dashboard');

        parent::tearDown();
    }

    #[Test]
    public function it_reports_unknown_with_no_heartbeat(): void
    {
        Cache::forget('scheduler_heartbeat');

        $this->assertSame('unknown', (new HealthCheckService)->checkScheduler()['status']);
    }

    #[Test]
    public function it_reports_ok_for_a_fresh_heartbeat(): void
    {
        Cache::put('scheduler_heartbeat', now()->subSeconds(30)->toIso8601String(), 120);

        $this->assertSame('ok', (new HealthCheckService)->checkScheduler()['status']);
    }

    #[Test]
    public function it_reports_stale_for_an_old_heartbeat(): void
    {
        Cache::put('scheduler_heartbeat', now()->subMinutes(10)->toIso8601String(), 600);

        $this->assertSame('stale', (new HealthCheckService)->checkScheduler()['status']);
    }

    #[Test]
    public function it_uses_the_configurable_stale_threshold(): void
    {
        Setting::updateOrCreate(
            ['group' => 'dashboard', 'key' => 'scheduler_stale_minutes'],
            ['value' => '5', 'type' => 'integer'],
        );
        app(SettingsService::class)->forget('dashboard');

        // 4 minutes old would be 'stale' under the default 3-minute
        // threshold, but 'ok' once the threshold is raised to 5.
        Cache::put('scheduler_heartbeat', now()->subMinutes(4)->toIso8601String(), 600);

        $this->assertSame('ok', (new HealthCheckService)->checkScheduler()['status']);
    }
}
