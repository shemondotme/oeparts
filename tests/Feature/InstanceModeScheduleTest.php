<?php

namespace Tests\Feature;

use App\Http\Middleware\TriggerDueScheduledTasks;
use App\Services\Instance\BlockLevel;
use App\Services\Instance\Contracts\BlockSource;
use App\Services\Instance\SafeMode;
use App\Services\Instance\SystemGate;
use App\Support\ScheduleCommandName;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Phase 4.4: the instance mode is a block source of the one SystemGate that already stands in front of every
 * scheduled event. The same classification of the real schedule gives three different answers by mode, and the
 * web cron fallback follows the mode rule while staying open during a freeze or an import.
 */
class InstanceModeScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SafeMode::resetLogMemory();
        Cache::forget('scheduler_heartbeat');
        Cache::forget('scheduler.http_fallback_lock');
    }

    protected function tearDown(): void
    {
        Cache::forget('scheduler_heartbeat');
        Cache::forget('scheduler.http_fallback_lock');

        parent::tearDown();
    }

    private function mode(?string $mode): void
    {
        config(['instance.mode' => $mode]);
    }

    /** @return array<int, Event> */
    private function realEvents(): array
    {
        $this->app->make(Kernel::class)->bootstrap();

        return app(Schedule::class)->events();
    }

    private function bare(Event $event): string
    {
        return ScheduleCommandName::for($event);
    }

    // ---- the gate sees the mode ----------------------------------------------------------------------------------

    #[Test]
    public function the_application_gate_has_the_instance_mode_registered_as_a_source(): void
    {
        $this->assertContains(SafeMode::class, array_map(fn ($s): string => $s::class, app(SystemGate::class)->sources()));
    }

    #[Test]
    public function the_block_level_follows_the_mode(): void
    {
        $safe = new SafeMode;

        $this->mode(null);
        $this->assertSame(BlockLevel::None, $safe->level());
        $this->mode('production');
        $this->assertSame(BlockLevel::None, $safe->level());
        $this->mode('staging');
        $this->assertSame(BlockLevel::CustomerOnly, $safe->level());
        $this->mode('quarantine');
        $this->assertSame(BlockLevel::Everything, $safe->level());
        $this->mode('stagging');
        $this->assertSame(BlockLevel::Everything, $safe->level(), 'a typo fails closed');
    }

    #[Test]
    public function the_gate_answers_the_same_as_safe_mode_for_every_class_in_every_mode(): void
    {
        $gate = app(SystemGate::class);
        $safe = new SafeMode;

        foreach ([null, 'production', 'staging', 'quarantine', 'stagging'] as $mode) {
            $this->mode($mode);

            foreach ([SystemGate::ALWAYS, SystemGate::INTERNAL, SystemGate::CUSTOMER, SystemGate::UNKNOWN] as $class) {
                $this->assertSame($safe->scheduledAllowed($class), $gate->allows($class), ($mode ?? 'unset')." / {$class}");
            }
        }
    }

    #[Test]
    public function over_the_real_schedule_production_runs_everything_staging_drops_customer_work_and_quarantine_keeps_only_the_healthy_class(): void
    {
        $gate = app(SystemGate::class);
        $events = $this->realEvents();
        $this->assertGreaterThan(20, count($events));

        $expected = [
            'production' => [SystemGate::ALWAYS => true, SystemGate::INTERNAL => true, SystemGate::CUSTOMER => true],
            'staging' => [SystemGate::ALWAYS => true, SystemGate::INTERNAL => true, SystemGate::CUSTOMER => false],
            'quarantine' => [SystemGate::ALWAYS => true, SystemGate::INTERNAL => false, SystemGate::CUSTOMER => false],
        ];

        foreach ($expected as $mode => $byClass) {
            $this->mode($mode);

            foreach ($events as $event) {
                $name = $this->bare($event);
                $class = $gate->classOf($name);
                $this->assertContains($class, array_keys($byClass), "{$name} is not classified");
                $this->assertSame($byClass[$class], $gate->allowsCommand($name), "{$mode}: {$name} ({$class})");
            }
        }
    }

    #[Test]
    public function a_real_customer_event_is_actually_skipped_on_a_staging_copy_and_a_heartbeat_always_runs(): void
    {
        $events = collect($this->realEvents());
        $newsletter = $events->first(fn (Event $e): bool => $this->bare($e) === 'oeparts:newsletter:send-due');
        $heartbeat = $events->first(fn (Event $e): bool => $this->bare($e) === 'scheduler:heartbeat');

        $this->mode('staging');
        $this->assertFalse($newsletter->filtersPass($this->app));
        $this->assertTrue($heartbeat->filtersPass($this->app));

        $this->mode('quarantine');
        $this->assertFalse($newsletter->filtersPass($this->app));
        $this->assertTrue($heartbeat->filtersPass($this->app));
    }

    #[Test]
    public function a_command_nobody_classified_is_held_on_a_copy_and_free_on_the_real_shop(): void
    {
        $gate = app(SystemGate::class);

        $this->mode('production');
        $this->assertTrue($gate->allowsCommand('mystery:task'));

        $this->mode('staging');
        $this->assertFalse($gate->allowsCommand('mystery:task'));
    }

    #[Test]
    public function the_reason_names_the_copy_for_the_admin_screens(): void
    {
        $this->mode('staging');

        $this->assertContains('this is a staging copy of the shop', app(SystemGate::class)->reasons());

        $this->mode('production');
        $this->assertNotContains('this is a staging copy of the shop', app(SystemGate::class)->reasons());
    }

    // ---- the web cron fallback --------------------------------------------------------------------------------------

    /** The real mode rule with the environment and install guards taken out, as the existing tests do. */
    private function cronBehindModeRuleOnly(): TriggerDueScheduledTasks
    {
        return new class extends TriggerDueScheduledTasks
        {
            protected function isSafeContext(): bool
            {
                return $this->modeAllowsHttpCron();
            }
        };
    }

    private function hit(TriggerDueScheduledTasks $middleware): void
    {
        $middleware->handle(Request::create('/'), fn (): Response => new Response('ok'));
        $middleware->terminate(Request::create('/'), new Response('ok'));
    }

    #[Test]
    #[DataProvider('cronByMode')]
    public function the_web_cron_runs_on_the_real_shop_and_not_on_a_copy_unless_asked_for(string $mode, bool $fires): void
    {
        config(['instance.nonprod_http_cron' => false]);
        $this->mode($mode);
        Artisan::spy();

        $this->hit($this->cronBehindModeRuleOnly());

        if ($fires) {
            Artisan::shouldHaveReceived('call')->with('schedule:run')->atLeast()->once();
        } else {
            Artisan::shouldNotHaveReceived('call', ['schedule:run']);
        }
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function cronByMode(): array
    {
        return ['production' => ['production', true], 'staging' => ['staging', false], 'quarantine' => ['quarantine', false], 'typo' => ['stagging', false]];
    }

    #[Test]
    public function a_copy_runs_the_web_cron_when_the_operator_opts_in(): void
    {
        config(['instance.nonprod_http_cron' => true]);
        $this->mode('staging');
        Artisan::spy();

        $this->hit($this->cronBehindModeRuleOnly());

        Artisan::shouldHaveReceived('call')->with('schedule:run')->atLeast()->once();
    }

    #[Test]
    public function a_freeze_or_an_import_never_closes_the_web_cron_door_only_the_per_event_gate_decides(): void
    {
        $middleware = $this->cronBehindModeRuleOnly();
        $reflect = new \ReflectionMethod($middleware, 'isSafeContext');

        $this->mode('production');
        app(SystemGate::class)->register(new class implements BlockSource
        {
            public function level(): BlockLevel
            {
                return BlockLevel::Everything;
            }

            public function reason(): string
            {
                return 'a test freeze';
            }
        });

        $this->assertTrue($reflect->invoke($middleware), 'the fallback stays open while something holds the site still');
        $this->assertFalse(app(SystemGate::class)->allowsCommand('oeparts:newsletter:send-due'));
        $this->assertTrue(app(SystemGate::class)->allowsCommand('scheduler:heartbeat'));
    }
}
