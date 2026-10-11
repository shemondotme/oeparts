<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Services\Backup\BackupLock;
use App\Services\Cleanup\CleanupContext;
use App\Services\Cleanup\CleanupJanitor;
use App\Services\Cleanup\CleanupManager;
use App\Services\Cleanup\CleanupScheduler;
use App\Services\Cleanup\Exceptions\CleanupBusyException;
use App\Services\Cleanup\Exceptions\CleanupException;
use App\Services\Cleanup\TargetRegistry;
use App\Services\Cleanup\Targets\DbPruneTarget;
use App\Services\SettingsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Cleanup Engine FSM end to end, on a scratch table: verified results, progress, resume,
 * cancel, locks, guards, the janitor and the auto scheduler.
 */
class CleanupManagerTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 3000;

    private const NEW = 1000;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-cleanupmgr-'.getmypid();
        @mkdir($this->state, 0775, true);
        config([
            'updates.state_path' => $this->state,
            'cleanup.chunk_rows' => 200,
            'cleanup.sleep_ms' => 0,
            'cleanup.window_start' => '00:00',
            'cleanup.window_end' => '00:00', // always open unless a test narrows it
        ]);

        foreach (['cleanup_scratch', 'cleanup_scratch_two'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->timestamp('created_at')->nullable();
            });
        }

        $this->seedTable('cleanup_scratch', self::OLD, self::NEW);
        $this->seedTable('cleanup_scratch_two', 600, 50);

        app()->instance(TargetRegistry::class, new TargetRegistry([
            ScratchTarget::class, ScratchTwoTarget::class, ReportOnlyTarget::class,
            StrictTarget::class, LockingTarget::class, ExplodingTarget::class,
        ]));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->state.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->state);
        foreach (glob(config('cleanup.work_dir').'/run-*.lock') ?: [] as $f) {
            @unlink($f);
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedTable(string $table, int $old, int $new): void
    {
        $rows = [];
        for ($i = 0; $i < $old; $i++) {
            $rows[] = ['created_at' => now()->subDays(100)];
        }
        for ($i = 0; $i < $new; $i++) {
            $rows[] = ['created_at' => now()->subDay()];
        }
        foreach (array_chunk($rows, 500) as $batch) {
            DB::table($table)->insert($batch);
        }
    }

    private function manager(): CleanupManager
    {
        return app(CleanupManager::class);
    }

    private function runToEnd(CleanupRun $run): CleanupRun
    {
        return $this->manager()->run($run, 60, 5);
    }

    /* ---- happy path / verification -------------------------------------- */

    #[Test]
    public function a_run_deletes_what_qualifies_and_proves_it(): void
    {
        $run = $this->runToEnd($this->manager()->start(['scratch']));

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(100, $run->percent);
        $this->assertNull($run->active_key, 'the unique key is released so the next cleanup can start');
        $this->assertSame(self::OLD, $run->targeted);
        $this->assertSame(self::OLD, $run->deleted);
        $this->assertSame(0, $run->remaining);

        $item = $run->items()->first();
        $this->assertSame(CleanupRunItem::STATUS_SUCCESS, $item->status);
        $this->assertSame(self::OLD, $item->targeted);
        $this->assertSame(self::OLD, $item->deleted);
        $this->assertSame(0, $item->remaining);
        $this->assertSame(0, $item->external_gone);

        // Independently of the engine's own bookkeeping:
        $this->assertSame(self::NEW, DB::table('cleanup_scratch')->count());
        $this->assertSame(0, DB::table('cleanup_scratch')->where('created_at', '<', now()->subDays(30))->count());
    }

    #[Test]
    public function a_dry_run_counts_but_deletes_nothing(): void
    {
        $run = $this->runToEnd($this->manager()->start(['scratch'], CleanupRun::MODE_DRY_RUN));

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(self::OLD, $run->targeted);
        $this->assertSame(0, $run->deleted);
        $this->assertSame(self::OLD + self::NEW, DB::table('cleanup_scratch')->count());
        $this->assertContains('dry_run', $run->items()->first()->reasons);
    }

    #[Test]
    public function progress_climbs_across_ticks_and_only_reaches_100_when_finished(): void
    {
        config(['cleanup.sleep_ms' => 60]); // makes the run span several half-second ticks

        $run = $this->manager()->start(['scratch']);
        $percents = [];
        $ticks = 0;

        while (! $run->isTerminal() && $ticks++ < 200) {
            $progress = $this->manager()->advance($run, 0.5);
            $run->refresh();
            $percents[] = $progress->percent;
            if (! $progress->done) {
                $this->assertLessThan(100, $progress->percent);
            }
        }

        $this->assertGreaterThan(1, count($percents), 'it really was spread over several ticks');
        $sorted = $percents;
        sort($sorted);
        $this->assertSame($sorted, $percents, 'progress never goes backwards');
        $this->assertSame(100, end($percents));
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
    }

    #[Test]
    public function a_run_survives_a_crash_and_continues_from_its_checkpoint(): void
    {
        config(['cleanup.sleep_ms' => 40]);
        $run = $this->manager()->start(['scratch']);

        $this->manager()->advance($run, 0.5); // part of the work...
        $this->assertFalse($run->refresh()->isTerminal());
        $partial = DB::table('cleanup_scratch')->count();
        $this->assertLessThan(self::OLD + self::NEW, $partial);

        // ...then the process "dies". A brand-new manager (new process) picks the run up.
        app()->forgetInstance(CleanupManager::class);
        $fresh = app(CleanupManager::class);
        config(['cleanup.sleep_ms' => 0]);
        $run = $fresh->run($run->fresh(), 60, 5);

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(self::OLD, $run->deleted, 'no row deleted twice, none missed');
        $this->assertSame(self::NEW, DB::table('cleanup_scratch')->count());
    }

    #[Test]
    public function rows_removed_by_someone_else_mid_run_are_reported_not_hidden(): void
    {
        config(['cleanup.sleep_ms' => 40]);
        $run = $this->manager()->start(['scratch']);
        $this->manager()->advance($run, 0.5);

        // An old logs:clean (or an admin) removes some of the same rows meanwhile.
        DB::table('cleanup_scratch')->where('created_at', '<', now()->subDays(30))->limit(0)->get();
        $victims = DB::table('cleanup_scratch')->where('created_at', '<', now()->subDays(30))->orderByDesc('id')->limit(100)->pluck('id');
        DB::table('cleanup_scratch')->whereIn('id', $victims)->delete();

        config(['cleanup.sleep_ms' => 0]);
        $run = $this->runToEnd($run->fresh());

        $item = $run->items()->first();
        $this->assertSame(CleanupRunItem::STATUS_SUCCESS, $item->status);
        $this->assertSame(100, $item->external_gone);
        $this->assertSame(self::OLD, $item->deleted + $item->external_gone + $item->remaining, 'targeted = deleted + external + remaining');
        $this->assertContains('external_deleted', $item->reasons);
    }

    /* ---- refusing -------------------------------------------------------- */

    #[Test]
    public function two_cleanups_can_never_run_at_once(): void
    {
        $first = $this->manager()->start(['scratch']);

        try {
            $this->manager()->start(['scratch_two']);
            $this->fail('a second cleanup must be refused while the first is active');
        } catch (CleanupBusyException $e) {
            $this->assertSame($first->id, $e->activeRunId);
            $this->assertStringContainsString("#{$first->id}", $e->getMessage());
        }

        $this->assertSame(1, CleanupRun::count());

        $this->runToEnd($first);
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $this->runToEnd($this->manager()->start(['scratch_two']))->status);
    }

    #[Test]
    public function an_unknown_or_report_only_or_non_truncatable_target_is_refused(): void
    {
        try {
            $this->manager()->start(['../../etc/passwd']);
            $this->fail('unknown key');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            $this->manager()->start(['report_only']);
            $this->fail('report-only target must not execute');
        } catch (CleanupException $e) {
            $this->assertStringContainsString('report-only', $e->getMessage());
        }

        // ...but a report-only target can be previewed.
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $this->runToEnd($this->manager()->start(['report_only'], CleanupRun::MODE_DRY_RUN))->status);

        try {
            $this->manager()->start(['strict'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_MANUAL, null, ['truncate' => true]);
            $this->fail('not truncatable');
        } catch (CleanupException $e) {
            $this->assertStringContainsString('cannot be wiped', $e->getMessage());
        }

        $this->assertSame(0, CleanupRun::where('status', CleanupRun::STATUS_RUNNING)->count(), 'refusals leave no run behind');
    }

    #[Test]
    public function truncate_wipes_everything_that_existed_when_it_started(): void
    {
        $run = $this->runToEnd($this->manager()->start(['scratch'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_MANUAL, null, ['truncate' => true]));

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(0, DB::table('cleanup_scratch')->count());
        $this->assertSame(self::OLD + self::NEW, $run->deleted);
    }

    #[Test]
    public function the_retention_floor_cannot_be_undercut_even_by_a_bad_setting(): void
    {
        // Admin (or a direct DB edit) sets 1 day; the target's floor is 7.
        DB::table('settings')->insert(['group' => 'cleanup', 'key' => 'retention_scratch', 'value' => '1', 'type' => 'integer']);
        app(SettingsService::class)->forget('cleanup');
        DB::table('cleanup_scratch')->insert(['created_at' => now()->subDays(3)]); // inside the floor

        $run = $this->runToEnd($this->manager()->start(['scratch']));

        $this->assertSame(7, $run->items()->first()->retention_days);
        $this->assertSame(1 + self::NEW, DB::table('cleanup_scratch')->where('created_at', '>=', now()->subDays(7))->count());
        $this->assertSame(0, DB::table('cleanup_scratch')->where('created_at', '<', now()->subDays(7))->count());
    }

    /* ---- cancel ---------------------------------------------------------- */

    #[Test]
    public function cancelling_stops_cleanly_and_reports_the_true_numbers(): void
    {
        config(['cleanup.sleep_ms' => 40]);
        $run = $this->manager()->start(['scratch']);
        $this->manager()->advance($run, 0.5);

        $this->manager()->cancel($run->refresh());
        config(['cleanup.sleep_ms' => 0]);
        $run = $this->runToEnd($run->fresh());

        $this->assertSame(CleanupRun::STATUS_CANCELLED, $run->status);
        $this->assertNull($run->active_key);
        $this->assertGreaterThan(0, $run->deleted);
        $this->assertGreaterThan(0, $run->remaining);
        $this->assertSame(self::OLD, $run->deleted + $run->remaining, 'the numbers add up even though it was stopped');
        $this->assertSame(self::NEW + $run->remaining, DB::table('cleanup_scratch')->count());
        $this->assertContains('cancelled_by_admin', $run->reasons);
    }

    #[Test]
    public function cancelling_before_the_second_target_starts_marks_it_cancelled(): void
    {
        $run = $this->manager()->start(['scratch', 'scratch_two']);
        $this->manager()->cancel($run);
        $run = $this->runToEnd($run->fresh());

        $this->assertSame(CleanupRun::STATUS_CANCELLED, $run->status);
        $this->assertSame(0, $run->deleted);
        $this->assertSame(650, DB::table('cleanup_scratch_two')->count());
        $this->assertSame([CleanupRunItem::STATUS_CANCELLED, CleanupRunItem::STATUS_CANCELLED], $run->items()->pluck('status')->all());
    }

    /* ---- busy system ----------------------------------------------------- */

    #[Test]
    public function it_refuses_to_start_while_a_backup_update_or_restore_runs(): void
    {
        app(BackupLock::class)->acquire('backup:9');

        $this->expectException(CleanupBusyException::class);
        $this->manager()->start(['scratch']);
    }

    #[Test]
    public function a_preview_may_run_while_a_backup_is_running_because_it_only_reads(): void
    {
        app(BackupLock::class)->acquire('backup:9');

        $run = $this->runToEnd($this->manager()->start(['scratch'], CleanupRun::MODE_DRY_RUN));

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(self::OLD, $run->targeted);
        $this->assertSame(self::OLD + self::NEW, DB::table('cleanup_scratch')->count());
    }

    #[Test]
    public function a_run_pauses_when_a_backup_starts_and_resumes_when_it_ends(): void
    {
        config(['cleanup.sleep_ms' => 40]);
        $run = $this->manager()->start(['scratch']);
        $this->manager()->advance($run, 0.5);
        $before = DB::table('cleanup_scratch')->count();

        app(BackupLock::class)->acquire('backup:9');
        $this->manager()->advance($run, 0.5);
        $run->refresh();

        $this->assertSame(CleanupRun::STATUS_PAUSED, $run->status);
        $this->assertSame('waiting_for_system', $run->paused_reason);
        $this->assertSame($before, DB::table('cleanup_scratch')->count(), 'nothing is deleted while the system is busy');
        $this->assertNotNull($run->active_key, 'a paused run still owns the slot');

        app(BackupLock::class)->release();
        config(['cleanup.sleep_ms' => 0]);
        $run = $this->runToEnd($run->fresh());

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(self::NEW, DB::table('cleanup_scratch')->count());
    }

    #[Test]
    public function file_family_targets_hold_the_shared_lock_for_their_run_and_release_it(): void
    {
        $lock = app(BackupLock::class);
        $run = $this->manager()->start(['locking']);

        $this->assertTrue($lock->isLocked());
        $this->assertSame($run->lockOwner(), $lock->owner()['owner']);

        $run = $this->runToEnd($run);

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status, 'its own lock must not make it pause itself');
        $this->assertFalse($lock->isLocked(), 'released at the end');
    }

    #[Test]
    public function it_never_releases_a_lock_that_is_not_its_own(): void
    {
        $run = $this->manager()->start(['locking']);
        // The lock was reclaimed and a backup grabbed it (e.g. after a janitor release).
        $lock = app(BackupLock::class);
        $lock->release();
        $lock->acquire('backup:77');

        $this->manager()->abandon($run, 'test', ['heartbeat_lost']);

        $this->assertTrue($lock->isLocked());
        $this->assertSame('backup:77', $lock->owner()['owner']);
    }

    #[Test]
    public function the_shared_lock_is_held_only_while_a_tick_works_never_between_ticks(): void
    {
        $lock = app(BackupLock::class);
        config(['cleanup.sleep_ms' => 40]);

        $run = $this->manager()->start(['locking']);
        $this->assertTrue($lock->isLocked(), 'taken at the start so a backup cannot begin underneath');

        $this->manager()->advance($run, 0.4);
        $run->refresh();
        $this->assertFalse($run->isTerminal(), 'long enough to need several ticks');
        $this->assertFalse($lock->isLocked(), 'between ticks a backup or an update is free to start');

        config(['cleanup.sleep_ms' => 0]);
        $run = $this->runToEnd($run);

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status, 'the lock is taken again for every tick');
        $this->assertFalse($lock->isLocked());
        $this->assertSame(self::NEW, DB::table('cleanup_scratch')->count());
    }

    #[Test]
    public function a_backup_that_starts_between_ticks_wins_and_the_run_waits(): void
    {
        $lock = app(BackupLock::class);
        config(['cleanup.sleep_ms' => 40]);

        $run = $this->manager()->start(['locking']);
        $this->manager()->advance($run, 0.4);
        $before = DB::table('cleanup_scratch')->count();

        $lock->acquire('backup:31'); // starts in the gap
        $this->manager()->advance($run, 0.4);
        $run->refresh();

        $this->assertSame(CleanupRun::STATUS_PAUSED, $run->status);
        $this->assertSame('backup:31', $lock->owner()['owner'], 'the backup lock is never touched');
        $this->assertSame($before, DB::table('cleanup_scratch')->count());

        $lock->release();
        config(['cleanup.sleep_ms' => 0]);
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $this->runToEnd($run->fresh())->status);
    }

    #[Test]
    public function a_run_that_waits_for_its_window_holds_no_lock(): void
    {
        config(['cleanup.window_start' => '02:00', 'cleanup.window_end' => '06:00']);
        Carbon::setTestNow(Carbon::parse('2026-10-12 03:00:00'));
        DB::table('cleanup_scratch')->insert(array_map(fn () => ['created_at' => now()->subDay()], range(1, 7000)));

        $run = $this->manager()->start(['locking'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_SCHEDULED);
        Carbon::setTestNow(Carbon::parse('2026-10-12 08:00:00')); // the window closed meanwhile
        $this->manager()->advance($run, 0.5);
        $run->refresh();

        $this->assertSame('outside_window', $run->paused_reason);
        $this->assertFalse(app(BackupLock::class)->isLocked(), 'the 01:00 backup must not be blocked by a run that is waiting for 02:00');

        Carbon::setTestNow(Carbon::parse('2026-10-13 03:00:00'));
        $run = $this->runToEnd($run->fresh());
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    #[Test]
    public function a_paused_run_can_still_be_cancelled(): void
    {
        $lock = app(BackupLock::class);
        $run = $this->manager()->start(['scratch']);
        $lock->acquire('backup:9');

        $this->manager()->advance($run, 0.5);
        $this->assertSame(CleanupRun::STATUS_PAUSED, $run->fresh()->status);

        $this->manager()->cancel($run->fresh());
        $this->manager()->advance($run->fresh(), 0.5);
        $run->refresh();

        $this->assertSame(CleanupRun::STATUS_CANCELLED, $run->status, 'cancel does not wait for the system to be idle');
        $this->assertNull($run->active_key, 'the slot is free again');
        $this->assertSame(self::OLD + self::NEW, DB::table('cleanup_scratch')->count(), 'cancelling deletes nothing');
        $this->assertSame('backup:9', $lock->owner()['owner'], 'the backup lock is untouched');
    }

    /* ---- automatic-run guards ------------------------------------------- */

    #[Test]
    public function a_scheduled_run_outside_the_window_waits_instead_of_deleting(): void
    {
        config(['cleanup.window_start' => '02:00', 'cleanup.window_end' => '06:00']);
        Carbon::setTestNow(Carbon::parse('2026-10-11 13:00:00'));
        // Keep the qualifying share well under the over-delete guard so only the window matters here.
        DB::table('cleanup_scratch')->insert(array_map(fn () => ['created_at' => now()->subDay()], range(1, 7000)));

        $run = $this->manager()->start(['scratch'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_SCHEDULED);
        $this->manager()->advance($run, 0.5);
        $run->refresh();

        $this->assertSame(CleanupRun::STATUS_PAUSED, $run->status);
        $this->assertSame('outside_window', $run->paused_reason);
        $this->assertSame(self::OLD + self::NEW + 7000, DB::table('cleanup_scratch')->count());

        Carbon::setTestNow(Carbon::parse('2026-10-12 03:00:00'));
        $run = $this->runToEnd($run->fresh());
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
    }

    #[Test]
    public function a_scheduled_run_will_not_wipe_most_of_a_table_but_a_manual_one_can(): void
    {
        // 3000 of 4000 rows qualify = 75% > the 50% guard, and >= 500 rows.
        $auto = $this->runToEnd($this->manager()->start(['scratch'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_SCHEDULED));

        $this->assertSame(CleanupRun::STATUS_PARTIAL, $auto->status, 'a skipped target means the run did not clean everything');
        $item = $auto->items()->first();
        $this->assertSame(CleanupRunItem::STATUS_SKIPPED_GUARD, $item->status);
        $this->assertContains('over_delete_pct', $item->reasons);
        $this->assertSame(self::OLD + self::NEW, DB::table('cleanup_scratch')->count(), 'nothing was deleted');
        $this->assertTrue(ActivityLog::where('action', 'cleanup.guard_abort')->exists());

        // The admin previews and runs it by hand: allowed.
        $manual = $this->runToEnd($this->manager()->start(['scratch']));
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $manual->status);
        $this->assertSame(self::NEW, DB::table('cleanup_scratch')->count());
    }

    #[Test]
    public function a_failing_target_is_reported_failed_and_the_run_moves_on(): void
    {
        $run = $this->runToEnd($this->manager()->start(['exploding', 'scratch_two']));

        $this->assertSame(CleanupRun::STATUS_FAILED, $run->status);
        $items = $run->items()->get()->keyBy('target');
        $this->assertSame(CleanupRunItem::STATUS_FAILED, $items['exploding']->status);
        $this->assertSame(CleanupRunItem::STATUS_SUCCESS, $items['scratch_two']->status, 'the next target still ran');
        $this->assertSame(50, DB::table('cleanup_scratch_two')->count());
        $this->assertStringContainsString('boom', implode(' ', $items['exploding']->reasons));
    }

    /* ---- audit / archive / notifications -------------------------------- */

    #[Test]
    public function start_and_finish_are_audited_once_each_not_per_chunk(): void
    {
        $admin = Admin::factory()->create();
        $run = $this->runToEnd($this->manager()->start(['scratch'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_MANUAL, $admin->id));

        $this->assertSame(1, ActivityLog::where('action', 'cleanup.start')->where('admin_id', $admin->id)->count());
        $finish = ActivityLog::where('action', 'cleanup.finish')->first();
        $this->assertSame(1, ActivityLog::where('action', 'cleanup.finish')->count());
        $this->assertSame($run->id, $finish->model_id);
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $finish->new_values['status']);
        $this->assertSame(self::OLD, $finish->new_values['deleted']);
        $this->assertLessThanOrEqual(3, ActivityLog::where('action', 'like', 'cleanup.%')->count());
    }

    #[Test]
    public function export_before_delete_archives_every_deleted_row(): void
    {
        $run = $this->runToEnd($this->manager()->start(['scratch'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_MANUAL, null, ['archive' => true]));

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $item = $run->items()->first();
        $this->assertGreaterThanOrEqual(self::OLD, $item->archive_rows);

        $gz = gzopen(Storage::disk('local')->path($item->archive_path), 'rb');
        $csv = '';
        while (! gzeof($gz)) {
            $csv .= gzread($gz, 65536);
        }
        gzclose($gz);
        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertCount(self::OLD + 1, $lines, 'header + one line per deleted row');
        $this->assertSame('id,created_at', $lines[0]);
    }

    #[Test]
    public function a_scheduled_partial_run_notifies_admins_once_and_a_manual_one_does_not(): void
    {
        Admin::factory()->create(['is_active' => true]);

        $this->runToEnd($this->manager()->start(['scratch'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_SCHEDULED)); // guard => partial
        $this->assertSame(1, DB::table('notifications')->count());

        // Same target + same outcome again within 24h: de-duplicated.
        $this->runToEnd($this->manager()->start(['scratch'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_SCHEDULED));
        $this->assertSame(1, DB::table('notifications')->count());

        DB::table('notifications')->delete();
        $this->runToEnd($this->manager()->start(['scratch_two'], CleanupRun::MODE_DRY_RUN, CleanupRun::TRIGGER_MANUAL));
        $this->assertSame(0, DB::table('notifications')->count());
    }

    /* ---- janitor --------------------------------------------------------- */

    #[Test]
    public function the_janitor_redrives_a_stale_run_to_completion(): void
    {
        $run = $this->manager()->start(['scratch']);
        CleanupRun::whereKey($run->id)->update(['updated_at' => now()->subHour()]);

        $count = app(CleanupJanitor::class)->reclaimStale();
        $run->refresh();

        $this->assertSame(1, $count);
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(self::NEW, DB::table('cleanup_scratch')->count());
    }

    #[Test]
    public function the_janitor_leaves_a_fresh_run_alone(): void
    {
        $run = $this->manager()->start(['scratch']);

        $this->assertSame(0, app(CleanupJanitor::class)->reclaimStale());
        $this->assertSame(CleanupRun::STATUS_RUNNING, $run->fresh()->status);
    }

    #[Test]
    public function a_run_that_makes_no_progress_for_hours_is_abandoned_and_frees_everything(): void
    {
        $lock = app(BackupLock::class);
        $run = $this->manager()->start(['locking']);
        $this->assertSame($run->lockOwner(), $lock->owner()['owner']);

        // A run nobody has advanced for 8 hours...
        $run->putMeta('last_progress_at', now()->subHours(8)->toIso8601String());
        $run->save();
        CleanupRun::whereKey($run->id)->update(['updated_at' => now()->subHours(8)]);

        // ...and whose driver is wedged: something else holds its per-run mutex, so the janitor's
        // re-drive cannot make progress either.
        @mkdir(config('cleanup.work_dir'), 0775, true);
        $wedged = fopen(config('cleanup.work_dir').'/run-'.$run->id.'.lock', 'c');
        $this->assertTrue(flock($wedged, LOCK_EX | LOCK_NB));

        try {
            app(CleanupJanitor::class)->reclaimStale();
        } finally {
            flock($wedged, LOCK_UN);
            fclose($wedged);
        }
        $run->refresh();

        $this->assertContains($run->status, [CleanupRun::STATUS_FAILED, CleanupRun::STATUS_PARTIAL]);
        $this->assertContains('heartbeat_lost', $run->reasons);
        $this->assertNull($run->active_key, 'the unique key is freed so cleanups are not blocked forever');
        $this->assertFalse($lock->isLocked(), 'its own shared lock is released so backups and updates can run');

        // A new cleanup can start and resume the leftovers with the same cutoff.
        $resumed = $this->manager()->resumeFrom($run);
        $this->assertSame($run->id, $resumed->resumed_from_id);
        $this->assertSame(CleanupRun::TRIGGER_RESUME, $resumed->trigger);
        $this->assertSame(
            $run->items()->first()->cutoff->toDateTimeString(),
            $resumed->items()->first()->cutoff->toDateTimeString(),
            'the same cutoff, so the same rows are targeted'
        );
    }

    #[Test]
    public function a_paused_run_gets_far_more_patience_than_a_stuck_one(): void
    {
        $run = $this->manager()->start(['scratch']);
        app(BackupLock::class)->acquire('backup:5'); // a long backup: the run waits for it
        $run->putMeta('last_progress_at', now()->subHours(8)->toIso8601String());
        $run->save();
        CleanupRun::whereKey($run->id)->update(['updated_at' => now()->subHours(8)]);

        app(CleanupJanitor::class)->reclaimStale();

        $this->assertSame(CleanupRun::STATUS_PAUSED, $run->fresh()->status, 'waiting for a backup is not a failure');
        $this->assertNotNull($run->fresh()->active_key);
    }

    /* ---- scheduler ------------------------------------------------------- */

    #[Test]
    public function the_scheduler_does_nothing_until_auto_cleanup_is_switched_on(): void
    {
        $this->assertSame('disabled', app(CleanupScheduler::class)->tick());
        $this->assertSame(0, CleanupRun::count());
    }

    #[Test]
    public function the_first_automatic_run_is_a_dry_run_and_real_deletion_waits_for_a_later_night(): void
    {
        // 600 of 650 qualify in scratch_two; the guard would stop it, so use auto for a small-share table instead.
        DB::table('cleanup_scratch')->insert(array_map(fn () => ['created_at' => now()->subDay()], range(1, 7000)));
        config(['cleanup.auto_enabled' => true]);
        $this->enableOnly('scratch');
        $total = self::OLD + self::NEW + 7000;

        $scheduler = app(CleanupScheduler::class);
        Carbon::setTestNow(Carbon::parse('2026-10-12 02:10:00'));

        $this->assertSame('started_dry_run', $scheduler->tick());
        $dry = CleanupRun::latest('id')->first();
        $this->assertSame(CleanupRun::MODE_DRY_RUN, $dry->mode);
        $this->assertSame(CleanupRun::TRIGGER_SCHEDULED, $dry->trigger);
        $this->assertSame($total, DB::table('cleanup_scratch')->count(), 'first night deletes nothing');

        // Fifteen minutes later nobody can have read the preview yet: still nothing, and no new preview either.
        Carbon::setTestNow(Carbon::parse('2026-10-12 02:25:00'));
        $this->assertSame('nothing_due', $scheduler->tick());
        $this->assertSame($total, DB::table('cleanup_scratch')->count());
        $this->assertSame(1, CleanupRun::count());

        // The next night the preview is a day old: now it really deletes.
        Carbon::setTestNow(Carbon::parse('2026-10-13 02:10:00'));
        $this->assertSame('started', $scheduler->tick());
        $this->assertSame(CleanupRun::MODE_EXECUTE, CleanupRun::latest('id')->first()->mode);
        $this->assertSame(self::NEW + 7000, DB::table('cleanup_scratch')->count());

        Carbon::setTestNow(Carbon::parse('2026-10-13 02:30:00'));
        $this->assertSame('nothing_due', $scheduler->tick(), 'cleaned within the last 20 hours');
    }

    #[Test]
    public function a_preview_an_admin_ran_by_hand_arms_the_target_at_once(): void
    {
        DB::table('cleanup_scratch')->insert(array_map(fn () => ['created_at' => now()->subDay()], range(1, 7000)));
        config(['cleanup.auto_enabled' => true]);
        $this->enableOnly('scratch');

        // The admin looked at exactly this: a manual preview.
        $this->runToEnd($this->manager()->start(['scratch'], CleanupRun::MODE_DRY_RUN, CleanupRun::TRIGGER_MANUAL));

        $this->assertSame('started', app(CleanupScheduler::class)->tick(), 'no second preview is needed');
        $this->assertSame(self::NEW + 7000, DB::table('cleanup_scratch')->count());
    }

    #[Test]
    public function a_manual_preview_from_months_ago_does_not_arm_a_target(): void
    {
        DB::table('cleanup_scratch')->insert(array_map(fn () => ['created_at' => now()->subDay()], range(1, 7000)));
        config(['cleanup.auto_enabled' => true]);
        $this->enableOnly('scratch');

        $this->runToEnd($this->manager()->start(['scratch'], CleanupRun::MODE_DRY_RUN, CleanupRun::TRIGGER_MANUAL));
        CleanupRunItem::query()->update(['finished_at' => now()->subDays(90)]);

        $this->assertSame('started_dry_run', app(CleanupScheduler::class)->tick(), 'a stale preview says nothing about today');
        $this->assertSame(self::OLD + self::NEW + 7000, DB::table('cleanup_scratch')->count());
    }

    #[Test]
    public function the_scheduler_respects_the_window_and_a_busy_system(): void
    {
        config(['cleanup.auto_enabled' => true, 'cleanup.window_start' => '02:00', 'cleanup.window_end' => '06:00']);
        $this->enableOnly('scratch');

        Carbon::setTestNow(Carbon::parse('2026-10-11 14:00:00'));
        $this->assertSame('outside_window', app(CleanupScheduler::class)->tick());

        Carbon::setTestNow(Carbon::parse('2026-10-12 03:00:00'));
        // The first automatic run of a target is a PREVIEW, which only reads and so may run during a backup...
        app(BackupLock::class)->acquire('backup:3');
        $this->assertSame('started_dry_run', app(CleanupScheduler::class)->tick());
        $this->assertSame(1, CleanupRun::count());

        // ...but the real deletion, the next night, waits for an idle system.
        Carbon::setTestNow(Carbon::parse('2026-10-13 03:00:00'));
        $this->assertSame('busy', app(CleanupScheduler::class)->tick());
        $this->assertSame(1, CleanupRun::count(), 'no second run was created');
        $this->assertSame(self::OLD + self::NEW, DB::table('cleanup_scratch')->count());
    }

    #[Test]
    public function the_scheduler_continues_a_run_nobody_is_driving_but_not_one_a_browser_is_polling(): void
    {
        $run = $this->manager()->start(['scratch']);
        $scheduler = app(CleanupScheduler::class);

        $this->assertSame('has_driver', $scheduler->tick(), 'heartbeat is fresh: a browser is polling it');
        $this->assertSame(CleanupRun::STATUS_RUNNING, $run->fresh()->status);

        CleanupRun::whereKey($run->id)->update(['updated_at' => now()->subMinutes(5)]);
        $this->assertSame('advanced', $scheduler->tick(5));
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->fresh()->status, 'works even with auto-clean switched off');
    }

    /** Turn the per-target auto flag off for everything except $key. */
    private function enableOnly(string $key): void
    {
        foreach (app(TargetRegistry::class)->all() as $target) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'cleanup', 'key' => 'auto_'.$target->key()],
                ['value' => $target->key() === $key ? '1' : '0', 'type' => 'boolean'],
            );
        }
        app(SettingsService::class)->forget('cleanup');
    }
}

/* ---- test targets ------------------------------------------------------- */

class ScratchTarget extends DbPruneTarget
{
    public function key(): string
    {
        return 'scratch';
    }

    public function label(): string
    {
        return 'Scratch rows';
    }

    public function description(): string
    {
        return 'Test rows.';
    }

    public function defaultDays(): int
    {
        return 30;
    }

    public function floorDays(): int
    {
        return 7;
    }

    public function truncatable(): bool
    {
        return true;
    }

    protected function table(): string
    {
        return 'cleanup_scratch';
    }

    protected function ageColumn(): string
    {
        return 'created_at';
    }
}

class ScratchTwoTarget extends ScratchTarget
{
    public function key(): string
    {
        return 'scratch_two';
    }

    protected function table(): string
    {
        return 'cleanup_scratch_two';
    }

    public function truncatable(): bool
    {
        return false;
    }
}

class ReportOnlyTarget extends ScratchTarget
{
    public function key(): string
    {
        return 'report_only';
    }

    public function reportOnly(): bool
    {
        return true;
    }
}

class StrictTarget extends ScratchTarget
{
    public function key(): string
    {
        return 'strict';
    }

    public function truncatable(): bool
    {
        return false;
    }
}

class LockingTarget extends ScratchTarget
{
    public function key(): string
    {
        return 'locking';
    }

    public function needsBackupLock(): bool
    {
        return true;
    }
}

class ExplodingTarget extends ScratchTarget
{
    public function key(): string
    {
        return 'exploding';
    }

    public function plan(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        throw new \RuntimeException('boom');
    }
}
