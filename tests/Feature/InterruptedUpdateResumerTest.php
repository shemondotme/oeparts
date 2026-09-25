<?php

namespace Tests\Feature;

use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use App\Services\Updates\InterruptedUpdateResumer;
use App\Services\Updates\RecoveryWindowFlag;
use App\Services\Updates\UpdateApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The apply FSM is poll-driven, but after the file swap the browser tab that
 * started it can no longer drive it (a Livewire upgrade between releases makes
 * every further poll a 419; a freshly loaded admin page used to 500 until the
 * migrations had run). Found by rehearsing the real 1.0.16 -> 2.0.0 self-update:
 * the site stayed in maintenance mode with new code on disk and an un-migrated
 * database. The release that just landed finishes its own update instead.
 */
class InterruptedUpdateResumerTest extends TestCase
{
    use RefreshDatabase;

    private string $state;

    private FakeUpdateApplier $applier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-resume-'.getmypid();
        @mkdir($this->state, 0775, true);
        config(['updates.state_path' => $this->state]);

        // complete()/fail() dispatch an admin-notification job (see UpdateApplierTest).
        Queue::fake();

        $this->applier = new FakeUpdateApplier;
        $this->app->instance(UpdateApplier::class, $this->applier);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->state.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->state);
        parent::tearDown();
    }

    private function manifest(): array
    {
        return ['version' => '1.1.0', 'channel' => 'stable', 'size_bytes' => 1024, 'migration_count' => 2,
            'download_url' => 'https://x/oeparts.zip', 'sha256' => str_repeat('a', 64)];
    }

    /** An update that has just completed its swap: new code on disk, finalize/verify still to run. */
    private function updateThatJustSwapped(int $secondsSinceLastCheckpoint = 30): UpdateHistory
    {
        $history = $this->applier->start($this->manifest(), initiatedBy: 1);
        foreach (['backup', 'download', 'extract', 'swap'] as $ignored) {
            $this->applier->advance($history);
        }

        $history = $history->refresh();
        $this->assertSame('finalize', $history->step);

        DB::table('update_histories')->where('id', $history->id)
            ->update(['updated_at' => now()->subSeconds($secondsSinceLastCheckpoint)]);

        $this->applier->log = []; // only what the RESUME drives is under test

        return $history->refresh();
    }

    private function resumer(): InterruptedUpdateResumer
    {
        return app(InterruptedUpdateResumer::class);
    }

    #[Test]
    public function it_finishes_a_post_swap_update_that_nothing_is_driving_any_more(): void
    {
        $history = $this->updateThatJustSwapped();
        $this->assertTrue((bool) settings('maintenance.enabled'), 'the site is in maintenance mode mid-update');

        $this->assertTrue($this->resumer()->resume());

        $history = $history->refresh();
        $this->assertSame(['finalize', 'verify'], $this->applier->log);
        $this->assertSame(UpdateHistory::STATUS_SUCCESS, $history->status);
        $this->assertSame('complete', $history->step);
        $this->assertFalse((bool) settings('maintenance.enabled'), 'maintenance is lifted once the update completes');
        $this->assertFalse(app(BackupLock::class)->isLocked(), 'the shared lock is released');
        $this->assertFalse(app(RecoveryWindowFlag::class)->isArmed(), 'the recovery window closes on success');
    }

    #[Test]
    public function it_is_a_no_op_when_no_update_window_is_open(): void
    {
        $history = $this->updateThatJustSwapped();
        app(RecoveryWindowFlag::class)->disarm();

        $this->assertFalse($this->resumer()->resume());

        $this->assertSame([], $this->applier->log);
        $this->assertSame('finalize', $history->refresh()->step);
    }

    #[Test]
    public function it_leaves_an_update_that_has_not_reached_the_swap_alone(): void
    {
        // Before the swap the OLD code is still running and its own tab is the
        // rightful driver — taking over here would let a stray request start
        // the backup/download on the admin's behalf.
        $history = $this->applier->start($this->manifest(), initiatedBy: 1);
        $this->applier->advance($history); // backup done -> step 'download'
        DB::table('update_histories')->where('id', $history->id)->update(['updated_at' => now()->subMinutes(5)]);
        $this->applier->log = [];

        $this->assertFalse($this->resumer()->resume());

        $this->assertSame([], $this->applier->log);
        $this->assertSame('download', $history->refresh()->step);
    }

    #[Test]
    public function it_gives_the_request_that_just_finished_the_swap_a_moment_to_finish(): void
    {
        $history = $this->updateThatJustSwapped(secondsSinceLastCheckpoint: 0);

        $this->assertFalse($this->resumer()->resume());

        $this->assertSame([], $this->applier->log);
        $this->assertSame('finalize', $history->refresh()->step);
    }

    #[Test]
    public function it_leaves_a_step_alone_that_another_request_is_already_running(): void
    {
        $history = $this->updateThatJustSwapped();

        // The non-blocking lock advance() takes — held by a poller mid-migration.
        $lock = Cache::lock('update_apply.advance.'.$history->getKey(), 60);
        $this->assertTrue($lock->get());

        $this->assertFalse($this->resumer()->resume());

        $lock->release();
        $this->assertSame([], $this->applier->log);
        $this->assertSame('finalize', $history->refresh()->step);
    }

    #[Test]
    public function a_failing_step_takes_the_normal_failure_path_instead_of_hanging(): void
    {
        $history = $this->updateThatJustSwapped();
        $this->applier->failAt = 'finalize';

        $this->assertTrue($this->resumer()->resume());

        $history = $history->refresh();
        $this->assertTrue($history->isTerminal(), 'a failed finalize must end in a terminal status');
        $this->assertNotSame(UpdateHistory::STATUS_SUCCESS, $history->status);
        $this->assertTrue($this->applier->rolledBack);
        $this->assertFalse((bool) settings('maintenance.enabled'), 'the site does not stay down after a rolled-back update');
    }

    #[Test]
    public function it_never_throws_into_the_request_that_triggered_it(): void
    {
        $this->updateThatJustSwapped();

        $this->app->instance(UpdateApplier::class, new class extends FakeUpdateApplier
        {
            public function advance(UpdateHistory $history): UpdateHistory
            {
                throw new \RuntimeException('boom');
            }
        });

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('error')->once()->withArgs(fn ($m) => str_contains($m, 'Resuming an interrupted update failed'));

        $this->assertFalse($this->resumer()->resume());
    }

    #[Test]
    public function the_first_request_to_reach_the_new_code_completes_the_update(): void
    {
        // The dying tab's next poll, an admin reload, a visitor shown the
        // maintenance page — any request will do, and none of them should notice.
        $history = $this->updateThatJustSwapped();

        $this->get('/up')->assertOk();

        $this->assertSame(UpdateHistory::STATUS_SUCCESS, $history->refresh()->status);
        $this->assertSame(['finalize', 'verify'], $this->applier->log);
    }

    #[Test]
    public function the_updates_page_view_does_not_depend_on_constants_the_previous_release_lacks(): void
    {
        // The request that swaps a release in renders the NEW view with the
        // PREVIOUS release's model classes still loaded (opcache/autoload hold them
        // for the rest of that request). A class constant the old model lacks is a
        // fatal there — 1.0.16 -> 2.0.0 answered its swap step with a 500 because
        // this view used UpdateHistory::TYPE_RESTORE, which 1.0.16 does not define.
        $view = file_get_contents(resource_path('views/filament/pages/system/system-updates.blade.php'));

        $this->assertDoesNotMatchRegularExpression(
            '/UpdateHistory::TYPE_/',
            $view,
            'Compare against the literal value instead — see the note next to the restore pill.'
        );
    }

    #[Test]
    public function the_hourly_watchdog_completes_a_finishable_update_instead_of_rolling_it_back(): void
    {
        $history = $this->updateThatJustSwapped(secondsSinceLastCheckpoint: 3 * 3600); // well past stale_after_seconds

        $this->artisan('oeparts:update:cleanup-stale')->assertSuccessful();

        $this->assertSame(UpdateHistory::STATUS_SUCCESS, $history->refresh()->status);
        $this->assertFalse($this->applier->rolledBack, 'a finishable update must be finished, not reverted');
    }
}
