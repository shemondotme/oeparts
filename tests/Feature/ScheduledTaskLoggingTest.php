<?php

namespace Tests\Feature;

use App\Enums\LogStatus;
use App\Filament\Pages\System\ScheduledTasksPage;
use App\Models\Admin;
use App\Models\CronLog;
use App\Support\ScheduleCommandName;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CronLogResource ships a full admin UI (list, view, "failed today" nav
 * badge), but nothing in routes/console.php ever wrote a CronLog row — the
 * page was permanently empty and the badge always read 0. Separately,
 * Event::$command stores the raw shell invocation (php binary + artisan
 * path + command), not the bare command name, so ScheduledTasksPage's "Run
 * Now" button was passing that whole string to Artisan::call() and always
 * throwing CommandNotFoundException.
 */
class ScheduledTaskLoggingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function schedule_command_name_extracts_the_bare_command_from_the_raw_shell_invocation(): void
    {
        $event = Schedule::command('scheduler:heartbeat');

        $this->assertSame('scheduler:heartbeat', ScheduleCommandName::for($event));
    }

    #[Test]
    public function a_successful_scheduled_task_writes_a_single_success_cron_log(): void
    {
        $event = Schedule::command('scheduler:heartbeat');

        Event::dispatch(new ScheduledTaskStarting($event));
        $event->exitCode = 0;
        Event::dispatch(new ScheduledTaskFinished($event, 0.42));

        $this->assertDatabaseCount('cron_logs', 1);
        $log = CronLog::first();
        $this->assertSame('scheduler:heartbeat', $log->job_name);
        $this->assertSame(LogStatus::Success, $log->status);
        $this->assertSame(420, $log->duration_ms);
    }

    #[Test]
    public function a_failing_scheduled_task_writes_exactly_one_failed_cron_log_not_two(): void
    {
        $event = Schedule::command('scheduler:heartbeat');

        Event::dispatch(new ScheduledTaskStarting($event));
        $event->exitCode = 1;
        // Mirrors ScheduleRunCommand::runEvent(): Finished always dispatches
        // first, then a non-zero exit code throws and Failed dispatches too —
        // both carry the SAME Event instance.
        Event::dispatch(new ScheduledTaskFinished($event, 0.1));
        Event::dispatch(new ScheduledTaskFailed($event, new \RuntimeException('boom')));

        $this->assertDatabaseCount('cron_logs', 1);
        $this->assertSame(LogStatus::Failed, CronLog::first()->status);
    }

    #[Test]
    public function running_a_task_now_from_the_admin_page_logs_it_and_does_not_crash(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(ScheduledTasksPage::class)
            ->call('runTask', 'scheduler:heartbeat');

        $this->assertDatabaseCount('cron_logs', 1);
        $log = CronLog::first();
        $this->assertSame('scheduler:heartbeat', $log->job_name);
        $this->assertSame(LogStatus::Success, $log->status);
    }

    #[Test]
    public function the_admin_page_task_list_exposes_the_bare_command_name(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $tasks = (new ScheduledTasksPage())->getScheduledTasks();

        $names = array_column($tasks, 'command');
        $this->assertContains('scheduler:heartbeat', $names);

        // None of the exposed command names should still be the raw shell
        // invocation (which always contains "artisan" as a quoted argument).
        foreach ($names as $name) {
            $this->assertStringNotContainsString('artisan', $name);
        }
    }
}
