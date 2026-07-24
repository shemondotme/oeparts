<?php

namespace Tests\Unit;

use App\Http\Middleware\TriggerDueScheduledTasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * HTTP-triggered cron fallback for a missing/broken system cron
 * (App\Http\Middleware\TriggerDueScheduledTasks). isSafeContext() is
 * overridden in these tests specifically to bypass the "never during
 * testing" guard — that guard is real production behavior (this middleware
 * is registered globally, so leaving it live during tests would risk every
 * HTTP test in the suite actually running schedule:run) and is verified
 * directly by the first test below, unmodified.
 */
class TriggerDueScheduledTasksTest extends TestCase
{
    private function alwaysSafe(): TriggerDueScheduledTasks
    {
        return new class extends TriggerDueScheduledTasks
        {
            protected function isSafeContext(): bool
            {
                return true;
            }
        };
    }

    /** Bypasses only the environment/lock-file guards, keeping the real config check. */
    private function safeIgnoringEnvironment(): TriggerDueScheduledTasks
    {
        return new class extends TriggerDueScheduledTasks
        {
            protected function isSafeContext(): bool
            {
                return (bool) config('app.cron_fallback_enabled', true);
            }
        };
    }

    protected function tearDown(): void
    {
        Cache::forget('scheduler_heartbeat');
        Cache::forget('scheduler.http_fallback_lock');

        parent::tearDown();
    }

    #[Test]
    public function it_never_fires_in_the_testing_environment_by_default(): void
    {
        Artisan::spy();
        Cache::forget('scheduler_heartbeat'); // no heartbeat = would otherwise be due

        (new TriggerDueScheduledTasks)->terminate(Request::create('/'), new Response);

        Artisan::shouldNotHaveReceived('call');
    }

    #[Test]
    public function it_triggers_schedule_run_when_no_heartbeat_has_ever_been_seen(): void
    {
        Artisan::spy();
        Cache::forget('scheduler_heartbeat');
        Cache::forget('scheduler.http_fallback_lock');

        $this->alwaysSafe()->terminate(Request::create('/'), new Response);

        Artisan::shouldHaveReceived('call')->with('schedule:run')->once();
    }

    #[Test]
    public function it_does_not_trigger_when_the_heartbeat_is_fresh(): void
    {
        Artisan::spy();
        Cache::put('scheduler_heartbeat', now()->toIso8601String(), 120);
        Cache::forget('scheduler.http_fallback_lock');

        $this->alwaysSafe()->terminate(Request::create('/'), new Response);

        Artisan::shouldNotHaveReceived('call');
    }

    #[Test]
    public function it_triggers_when_the_heartbeat_is_stale(): void
    {
        Artisan::spy();
        Cache::put('scheduler_heartbeat', now()->subMinutes(5)->toIso8601String(), 600);
        Cache::forget('scheduler.http_fallback_lock');

        $this->alwaysSafe()->terminate(Request::create('/'), new Response);

        Artisan::shouldHaveReceived('call')->with('schedule:run')->once();
    }

    #[Test]
    public function it_is_throttled_and_does_not_fire_twice_within_the_window(): void
    {
        Artisan::spy();
        Cache::forget('scheduler_heartbeat');
        Cache::put('scheduler.http_fallback_lock', true, 60);

        $this->alwaysSafe()->terminate(Request::create('/'), new Response);

        Artisan::shouldNotHaveReceived('call');
    }

    #[Test]
    public function it_disables_itself_via_config(): void
    {
        config(['app.cron_fallback_enabled' => false]);
        Artisan::spy();
        Cache::forget('scheduler_heartbeat');

        $this->safeIgnoringEnvironment()->terminate(Request::create('/'), new Response);

        Artisan::shouldNotHaveReceived('call');
    }
}
