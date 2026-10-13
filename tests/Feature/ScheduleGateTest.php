<?php

namespace Tests\Feature;

use App\Services\Instance\BlockLevel;
use App\Services\Instance\Contracts\BlockSource;
use App\Services\Instance\ScheduleGate;
use App\Services\Instance\SystemGate;
use App\Support\ScheduleCommandName;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.10b: the gate in front of every scheduled command. It loads the REAL routes/console.php, so
 * a command added next month that nobody classified fails the build here instead of slipping through a
 * freeze, and an entry for a command that no longer exists is flagged as stale.
 */
class ScheduleGateTest extends TestCase
{
    /** @return array<int, Event> */
    private function realEvents(): array
    {
        Artisan::call('list'); // boots the console kernel, which loads routes/console.php

        return app(Schedule::class)->events();
    }

    private function bareName(Event $event): string
    {
        return (string) strtok(ScheduleCommandName::for($event), ' ');
    }

    private function source(BlockLevel $level, string $reason = 'a test hold'): BlockSource
    {
        return new class($level, $reason) implements BlockSource
        {
            public function __construct(public BlockLevel $level, private readonly string $reason) {}

            public function level(): BlockLevel
            {
                return $this->level;
            }

            public function reason(): string
            {
                return $this->reason;
            }
        };
    }

    // ---- the real schedule is fully classified ---------------------------------------------------

    #[Test]
    public function the_real_schedule_is_loaded_and_every_event_is_classified_exactly_once(): void
    {
        $events = $this->realEvents();
        $this->assertGreaterThanOrEqual(26, count($events), 'routes/console.php is loaded: the schedule is populated');

        $gate = new SystemGate;
        $unclassified = [];

        foreach ($events as $event) {
            if ($gate->classOf($this->bareName($event)) === SystemGate::UNKNOWN) {
                $unclassified[] = $this->bareName($event);
            }
        }

        $this->assertSame([], $unclassified, 'classify these in config/instance.php (always, internal or customer)');
    }

    #[Test]
    public function no_entry_in_the_classification_is_stale_and_none_is_listed_twice(): void
    {
        $scheduled = array_map(fn ($event) => $this->bareName($event), $this->realEvents());
        $listed = [];

        foreach (['always', 'internal', 'customer'] as $class) {
            foreach (config("instance.schedule.{$class}") as $command) {
                $listed[] = $command;
            }
        }

        $this->assertSame([], array_values(array_diff($listed, $scheduled)), 'listed in config/instance.php but not scheduled any more');
        $this->assertSame($listed, array_values(array_unique($listed)), 'a command in two classes');
    }

    #[Test]
    public function the_things_that_must_keep_running_while_everything_else_is_held_are_always(): void
    {
        $gate = new SystemGate;

        foreach (['oeparts:backup:cleanup-stale', 'oeparts:update:cleanup-stale', 'oeparts:cleanup:cleanup-stale', 'scheduler:heartbeat'] as $command) {
            $this->assertSame(SystemGate::ALWAYS, $gate->classOf($command), $command);
        }

        foreach (['oeparts:newsletter:send-due', 'oeparts:invoices:remind', 'oeparts:update:auto-apply', 'oeparts:admin-push:flush', 'abandoned-cart:process'] as $command) {
            $this->assertSame(SystemGate::CUSTOMER, $gate->classOf($command), $command);
        }
    }

    #[Test]
    public function the_migration_watchdog_and_janitor_keep_running_while_the_shop_is_frozen(): void
    {
        $gate = new SystemGate;
        $gate->register($this->source(BlockLevel::Everything));

        foreach (['oeparts:migrate:watchdog', 'oeparts:migrate:janitor'] as $command) {
            $this->assertSame(SystemGate::ALWAYS, $gate->classOf($command), $command);
            $this->assertTrue($gate->allowsCommand($command), "{$command} must be what ends a freeze");
        }
    }

    // ---- the gate's rules ---------------------------------------------------------------------------

    #[Test]
    public function with_nothing_registered_every_command_is_allowed_including_one_nobody_has_heard_of(): void
    {
        $gate = new SystemGate;

        $this->assertFalse($gate->isBlocking());
        $this->assertTrue($gate->allowsCommand('mystery:task'));
        $this->assertTrue($gate->allowsCommand('oeparts:newsletter:send-due'));
    }

    #[Test]
    public function while_everything_is_held_only_the_always_class_runs_and_an_unknown_command_is_held_too(): void
    {
        $gate = new SystemGate;
        $gate->register($this->source(BlockLevel::Everything));

        $this->assertTrue($gate->allowsCommand('scheduler:heartbeat'));
        $this->assertFalse($gate->allowsCommand('sitemap:generate'));
        $this->assertFalse($gate->allowsCommand('oeparts:newsletter:send-due'));
        $this->assertFalse($gate->allowsCommand('mystery:task'), 'unknown commands fail closed');
    }

    #[Test]
    public function on_a_staging_copy_internal_housekeeping_runs_but_nothing_that_reaches_a_customer(): void
    {
        $gate = new SystemGate;
        $gate->register($this->source(BlockLevel::CustomerOnly));

        $this->assertTrue($gate->allowsCommand('scheduler:heartbeat'));
        $this->assertTrue($gate->allowsCommand('sitemap:generate'));
        $this->assertFalse($gate->allowsCommand('oeparts:newsletter:send-due'));
        $this->assertFalse($gate->allowsCommand('mystery:task'));
    }

    #[Test]
    public function the_highest_level_wins_and_a_source_that_stops_blocking_lets_everything_through_again(): void
    {
        $gate = new SystemGate;
        $mild = $this->source(BlockLevel::CustomerOnly, 'a staging copy');
        $hard = $this->source(BlockLevel::Everything, 'an export freeze');
        $gate->register($mild);
        $gate->register($hard);

        $this->assertSame(BlockLevel::Everything, $gate->level());
        $this->assertSame(['a staging copy', 'an export freeze'], $gate->reasons());

        $hard->level = BlockLevel::None;
        $this->assertSame(BlockLevel::CustomerOnly, $gate->level());
        $this->assertSame(['a staging copy'], $gate->reasons());

        $mild->level = BlockLevel::None;
        $this->assertFalse($gate->isBlocking());
        $this->assertTrue($gate->allowsCommand('oeparts:newsletter:send-due'));
    }

    #[Test]
    public function arguments_do_not_change_what_class_a_command_is(): void
    {
        $this->assertSame(SystemGate::INTERNAL, (new SystemGate)->classOf('oeparts:backup --trigger=scheduled'));
    }

    // ---- the gate in front of the real events --------------------------------------------------------

    #[Test]
    public function the_gate_really_stands_in_front_of_every_real_event(): void
    {
        $events = $this->realEvents();
        $gate = app(SystemGate::class);
        $hold = $this->source(BlockLevel::Everything);
        $gate->register($hold);

        try {
            foreach ($events as $event) {
                $name = $this->bareName($event);

                if ($gate->classOf($name) === SystemGate::ALWAYS) {
                    continue;
                }

                $this->assertFalse($event->filtersPass($this->app), "{$name} ran through a full hold");
            }

            $hold->level = BlockLevel::None;
            $heartbeat = collect($events)->first(fn ($e) => $this->bareName($e) === 'scheduler:heartbeat');
            $this->assertTrue($heartbeat->filtersPass($this->app));
        } finally {
            $hold->level = BlockLevel::None;
        }
    }

    #[Test]
    public function a_command_added_later_without_a_class_is_held_by_a_hold_and_free_without_one(): void
    {
        $gate = new SystemGate;
        $schedule = new Schedule;
        $event = $schedule->command('mystery:task');
        (new ScheduleGate($gate))->apply($schedule);

        $this->assertTrue($event->filtersPass($this->app));

        $gate->register($this->source(BlockLevel::CustomerOnly));
        $this->assertFalse($event->filtersPass($this->app));
    }

    #[Test]
    public function the_gate_keeps_the_conditions_an_event_already_had(): void
    {
        $gate = new SystemGate;
        $schedule = new Schedule;
        $switchedOff = $schedule->command('scheduler:heartbeat')->when(fn () => false);
        $switchedOn = $schedule->command('health:snapshot')->when(fn () => true);
        (new ScheduleGate($gate))->apply($schedule);

        $this->assertFalse($switchedOff->filtersPass($this->app), 'its own condition still applies, gate or no gate');
        $this->assertTrue($switchedOn->filtersPass($this->app));

        $gate->register($this->source(BlockLevel::Everything));
        $this->assertFalse($switchedOn->filtersPass($this->app));
    }
}
