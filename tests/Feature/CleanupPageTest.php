<?php

namespace Tests\Feature;

use App\Filament\Pages\System\CleanupDashboard;
use App\Filament\Widgets\Cleanup\CleanupBackupPlanTable;
use App\Filament\Widgets\Cleanup\CleanupFileLogsTable;
use App\Filament\Widgets\Cleanup\CleanupHistoryTable;
use App\Filament\Widgets\Cleanup\CleanupRunItemsTable;
use App\Filament\Widgets\Cleanup\CleanupStats;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\CleanupRun;
use App\Models\CleanupTargetStat;
use App\Services\Backup\BackupLock;
use App\Services\Cleanup\CleanupManager;
use App\Services\Cleanup\TargetRegistry;
use App\Services\SettingsService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Cleanup page, driven the way a browser drives it: Livewire calls, table and modal actions with their confirmations,
 * and the poll loop that advances a run. The page is built from stock Filament parts, so the log-file, backup, history and
 * run-result tables are separate Livewire components and are tested on their own below.
 */
class CleanupPageTest extends TestCase
{
    use RefreshDatabase;

    private string $sandbox;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, RolesSeeder::class]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('local');

        // Everything the file targets and the update system touch lives in a sandbox, never the real project dirs.
        $this->sandbox = storage_path('framework/testing/cleanup-page-'.getmypid());
        $this->rrmdir($this->sandbox);
        mkdir($this->sandbox.'/logs', 0775, true);
        mkdir($this->sandbox.'/state', 0775, true);
        mkdir($this->sandbox.'/root', 0775, true);
        config([
            'cleanup.log_dir' => $this->sandbox.'/logs',
            'updates.state_path' => $this->sandbox.'/state',
            'updates.root_path' => $this->sandbox.'/root',
            'cleanup.chunk_rows' => 200,
            'cleanup.sleep_ms' => 0,
        ]);

        $this->admin = Admin::factory()->create(['is_active' => true, 'password' => Hash::make('correct-horse')]);
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin, 'admin');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->sandbox);
        foreach (glob(config('cleanup.work_dir').'/run-*.lock') ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function rrmdir(string $path): void
    {
        if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
            @unlink($path);

            return;
        }
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $e) {
                if ($e !== '.' && $e !== '..') {
                    $this->rrmdir($path.DIRECTORY_SEPARATOR.$e);
                }
            }
            @rmdir($path);
        }
    }

    /** Make a run span several polls: the seeded settings (not config) win, so change those. */
    private function slow(): void
    {
        $this->setting('chunk_rows', '200');
        $this->setting('sleep_ms', '100');
        config(['cleanup.tick_seconds' => 0.5]);
    }

    private function fast(): void
    {
        $this->setting('sleep_ms', '0');
        config(['cleanup.tick_seconds' => 3]);
    }

    private function setting(string $key, string $value): void
    {
        DB::table('settings')->where(['group' => 'cleanup', 'key' => $key])->update(['value' => $value]);
        app(SettingsService::class)->forget('cleanup');
    }

    private function cronRows(int $old, int $recent = 0): void
    {
        $rows = [];
        for ($i = 0; $i < $old; $i++) {
            $rows[] = ['job_name' => 'j', 'status' => 'success', 'duration_ms' => 1, 'ran_at' => now()->subDays(100)];
        }
        for ($i = 0; $i < $recent; $i++) {
            $rows[] = ['job_name' => 'j', 'status' => 'success', 'duration_ms' => 1, 'ran_at' => now()->subHour()];
        }
        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('cron_logs')->insert($batch);
        }
    }

    /** Poll like the browser does until the run ends. */
    private function pollToEnd($component, int $max = 200)
    {
        for ($i = 0; $i < $max && $component->get('running'); $i++) {
            $component->call('pollRun');
        }

        return $component;
    }

    #[Test]
    public function the_page_shows_every_tab_and_the_targets(): void
    {
        $this->get(CleanupDashboard::getUrl())
            ->assertOk()
            ->assertSee('What can be cleaned')
            ->assertSee('Search logs')
            ->assertSee('Clean up')
            ->assertSee('Log files')
            ->assertSee('Backups')
            ->assertSee('History')
            ->assertSee('Automatic cleaning &amp; safety settings', false)
            ->assertSee('Dev-only files')
            ->assertSee('Database schema audit')
            ->assertSee('Scheduled maintenance tasks');
    }

    #[Test]
    public function the_targets_table_can_show_each_group(): void
    {
        Livewire::test(CleanupDashboard::class)
            ->assertSee('Search logs')
            ->assertDontSee('Stale sitemap files')
            ->filterTable('scope', ['group' => 'files'])
            ->assertSee('Stale sitemap files')
            ->assertDontSee('Search logs')
            ->filterTable('scope', ['group' => 'backups'])
            ->assertSee('Backups beyond the retention policy')
            ->filterTable('scope', ['group' => 'all'])
            ->assertSee('Search logs')
            ->assertSee('Stale sitemap files');
    }

    #[Test]
    public function a_role_without_manage_cleanup_cannot_use_it(): void
    {
        $support = Admin::factory()->create(['is_active' => true]);
        $support->assignRole('support');
        $this->actingAs($support, 'admin');

        $this->get(CleanupDashboard::getUrl())->assertForbidden();

        foreach ([CleanupStats::class, CleanupFileLogsTable::class, CleanupBackupPlanTable::class, CleanupHistoryTable::class, CleanupRunItemsTable::class] as $widget) {
            $this->assertFalse($widget::canView(), $widget.' must not be viewable without the permission');
        }
    }

    #[Test]
    public function the_run_id_cannot_be_forged_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(CleanupDashboard::class)->set('runId', 99);
    }

    /* ---- preview -> delete ------------------------------------------- */

    #[Test]
    public function a_preview_counts_without_deleting_and_then_offers_to_delete_exactly_that(): void
    {
        $this->cronRows(450, 20);

        $component = Livewire::test(CleanupDashboard::class)
            ->callTableAction('preview', 'cron_logs')
            ->assertSet('running', true);
        $this->pollToEnd($component);

        $run = CleanupRun::latest('id')->first();
        $this->assertSame(CleanupRun::MODE_DRY_RUN, $run->mode);
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(450, $run->targeted);
        $this->assertSame(470, DB::table('cron_logs')->count(), 'a preview deletes nothing');

        $component->assertSet('running', false)->assertSee('Delete these 450 now');

        // "Delete these now": a typed confirmation is required because the amount was never measured on this page...
        $component->callAction(TestAction::make('deletePreviewed')->schemaComponent('run-section.run-actions', schema: 'content'), data: ['confirm' => 'nope'])->assertHasActionErrors(['confirm']);
        $this->assertSame(470, DB::table('cron_logs')->count());

        $component->callAction(TestAction::make('deletePreviewed')->schemaComponent('run-section.run-actions', schema: 'content'), data: ['confirm' => 'DELETE'])->assertHasNoActionErrors();
        $this->pollToEnd($component);

        $real = CleanupRun::latest('id')->first();
        $this->assertSame(CleanupRun::MODE_EXECUTE, $real->mode);
        $this->assertSame($run->id, $real->preview_run_id, 'it deletes what was previewed');
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $real->status);
        $this->assertSame(450, $real->deleted);
        $this->assertSame(20, DB::table('cron_logs')->count());
        $component->assertSee('Completed &amp; verified', false)->assertSee('Found 450 = removed 450');
    }

    #[Test]
    public function a_small_cleanup_whose_size_is_known_needs_only_a_plain_confirmation(): void
    {
        $this->cronRows(300);
        CleanupTargetStat::create(['target' => 'cron_logs', 'rows_est' => 300, 'eligible_rows' => 300, 'measured_at' => now()]);

        $component = Livewire::test(CleanupDashboard::class)
            ->callTableAction('clean', 'cron_logs')
            ->assertHasNoTableActionErrors();
        $this->pollToEnd($component);

        $this->assertSame(0, DB::table('cron_logs')->count());
    }

    #[Test]
    public function a_wrong_typed_confirmation_never_starts_a_cleanup(): void
    {
        $this->cronRows(300);

        Livewire::test(CleanupDashboard::class)
            ->callTableAction('clean', 'cron_logs', data: ['confirm' => 'delete'])
            ->assertHasTableActionErrors(['confirm']);

        $this->assertSame(0, CleanupRun::count());
        $this->assertSame(300, DB::table('cron_logs')->count());
    }

    /* ---- progress, cancel, resume ------------------------------------ */

    #[Test]
    public function the_run_panel_shows_progress_while_polling_and_the_result_when_done(): void
    {
        $this->slow();
        $this->cronRows(1500);

        $component = Livewire::test(CleanupDashboard::class)
            ->callTableAction('clean', 'cron_logs', data: ['confirm' => 'DELETE']);

        $component->assertSee('wire:poll.2s="pollRun"', false);
        $component->call('pollRun');
        $run = CleanupRun::latest('id')->first();
        $this->assertContains($run->status, [CleanupRun::STATUS_RUNNING, CleanupRun::STATUS_SUCCESS]);

        $this->pollToEnd($component);

        $component->assertDontSee('wire:poll.2s="pollRun"', false)->assertSee('Completed &amp; verified', false);
        $this->assertSame(100, CleanupRun::latest('id')->first()->percent);
    }

    #[Test]
    public function an_admin_can_cancel_and_then_resume_what_is_left(): void
    {
        $this->slow();
        $this->cronRows(2500);

        $component = Livewire::test(CleanupDashboard::class)
            ->callTableAction('clean', 'cron_logs', data: ['confirm' => 'DELETE']);
        $component->call('pollRun');
        $first = CleanupRun::latest('id')->first();
        $this->assertFalse($first->refresh()->isTerminal(), 'long enough to still be running');

        $component->call('cancelRun');
        $this->fast();
        $this->pollToEnd($component);

        $cancelled = $first->fresh();
        $this->assertSame(CleanupRun::STATUS_CANCELLED, $cancelled->status);
        $left = DB::table('cron_logs')->count();
        $this->assertGreaterThan(0, $left);
        $component->assertSee('Resume what is left');

        $component->call('resumeRun');
        $this->pollToEnd($component);

        $resumed = CleanupRun::latest('id')->first();
        $this->assertSame($first->id, $resumed->resumed_from_id);
        $this->assertSame(CleanupRun::STATUS_SUCCESS, $resumed->status);
        $this->assertSame(0, DB::table('cron_logs')->count());
    }

    #[Test]
    public function a_run_in_progress_is_picked_up_again_after_a_reload(): void
    {
        $this->slow();
        $this->cronRows(2500);
        Livewire::test(CleanupDashboard::class)
            ->callTableAction('clean', 'cron_logs', data: ['confirm' => 'DELETE']);
        $run = CleanupRun::latest('id')->first();

        // A fresh page load (new component instance) finds the active run and resumes polling it.
        Livewire::test(CleanupDashboard::class)->assertSet('runId', $run->id)->assertSet('running', true);
    }

    /* ---- refusals ---------------------------------------------------- */

    #[Test]
    public function a_real_cleanup_is_refused_while_a_backup_runs_but_a_preview_is_not(): void
    {
        $this->cronRows(300);
        app(BackupLock::class)->acquire('backup:4');

        $component = Livewire::test(CleanupDashboard::class)
            ->callTableAction('clean', 'cron_logs', data: ['confirm' => 'DELETE'])
            ->assertNotified();

        $this->assertSame(0, CleanupRun::count(), 'no run was created');
        $this->assertSame(300, DB::table('cron_logs')->count());

        $component->call('startPreview', 'cron_logs')->assertSet('running', true);
        $this->assertSame(1, CleanupRun::count());
    }

    #[Test]
    public function an_unknown_or_protected_target_key_from_the_browser_is_refused_not_executed(): void
    {
        Livewire::test(CleanupDashboard::class)
            ->call('startPreview', '../../etc/passwd')
            ->assertSet('running', false)
            ->assertNotified();

        // A business table is not a cleanup target at all.
        Livewire::test(CleanupDashboard::class)
            ->call('startPreview', 'orders')
            ->assertSet('running', false)
            ->assertNotified();

        $this->assertSame(0, CleanupRun::count());
    }

    #[Test]
    public function deleting_all_rows_needs_the_typed_word_and_the_admins_password(): void
    {
        $this->cronRows(300, 40);
        Livewire::test(CleanupDashboard::class)
            ->callTableAction('deleteAll', 'cron_logs', data: ['confirm' => 'DELETE', 'password' => 'wrong']);
        $this->assertSame(340, DB::table('cron_logs')->count());
        $this->assertSame(0, CleanupRun::count(), 'a wrong password starts nothing');

        $component = Livewire::test(CleanupDashboard::class)
            ->callTableAction('deleteAll', 'cron_logs', data: ['confirm' => 'DELETE', 'password' => 'correct-horse']);
        $this->pollToEnd($component);

        $this->assertSame(0, DB::table('cron_logs')->count(), 'every row that existed is gone');
        $this->assertTrue((bool) CleanupRun::latest('id')->first()->getMetaValue('truncate'));
    }

    #[Test]
    public function the_activity_log_cannot_be_wiped_all_at_once_from_the_page(): void
    {
        Livewire::test(CleanupDashboard::class)
            ->assertTableActionHidden('deleteAll', 'activity_logs')
            ->assertTableActionVisible('deleteAll', 'cron_logs');

        $this->assertSame(0, CleanupRun::count());
    }

    #[Test]
    public function the_header_can_clean_everything_that_is_due_only_with_the_typed_word(): void
    {
        $this->cronRows(300);

        Livewire::test(CleanupDashboard::class)
            ->callAction('cleanAll', data: ['confirm' => 'nope'])
            ->assertHasActionErrors(['confirm']);
        $this->assertSame(0, CleanupRun::count());

        Livewire::test(CleanupDashboard::class)
            ->callAction('cleanAll', data: ['confirm' => 'DELETE'])
            ->assertHasNoActionErrors()
            ->assertSet('running', true);
        $this->assertSame(1, CleanupRun::count());
    }

    /* ---- resume and run-everything respect rights -------------------- */

    /** An admin who may clean but may not touch backups. */
    private function cleanupOnlyAdmin(): Admin
    {
        $limited = Admin::factory()->create(['is_active' => true]);
        $limited->givePermissionTo('manage cleanup');

        return $limited;
    }

    /** A finished (cancelled) run of one backup-group target, started by the super admin. */
    private function cancelledBackupRun(): CleanupRun
    {
        $key = collect(app(TargetRegistry::class)->all())
            ->first(fn ($t) => $t->group() === 'backups' && ! $t->reportOnly())
            ->key();

        $manager = app(CleanupManager::class);
        $run = $manager->start([$key], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_MANUAL, $this->admin->id);
        $manager->cancel($run);
        $manager->advance($run->fresh(), 2);

        $this->assertSame(CleanupRun::STATUS_CANCELLED, $run->fresh()->status);

        return $run->fresh();
    }

    #[Test]
    public function resuming_a_backup_target_run_needs_the_manage_backups_permission(): void
    {
        $run = $this->cancelledBackupRun();
        $before = CleanupRun::count();

        $this->actingAs($this->cleanupOnlyAdmin(), 'admin');
        Livewire::test(CleanupDashboard::class)
            ->call('showRun', $run->id)
            ->call('resumeRun')
            ->assertNotified('Not allowed');

        $this->assertSame($before, CleanupRun::count(), 'nothing was started');

        // The super admin (who has the permission) can resume the very same run.
        $this->actingAs($this->admin, 'admin');
        Livewire::test(CleanupDashboard::class)
            ->call('showRun', $run->id)
            ->call('resumeRun')
            ->assertSet('running', true);
        $this->assertSame($before + 1, CleanupRun::count());
    }

    #[Test]
    public function a_delete_all_run_cannot_be_resumed_with_one_click(): void
    {
        $this->cronRows(300, 40);
        $manager = app(CleanupManager::class);
        $run = $manager->start(['cron_logs'], CleanupRun::MODE_EXECUTE, CleanupRun::TRIGGER_MANUAL, $this->admin->id, ['truncate' => true]);
        $manager->cancel($run);
        $manager->advance($run->fresh(), 2);
        $before = CleanupRun::count();

        Livewire::test(CleanupDashboard::class)
            ->call('showRun', $run->id)
            ->call('resumeRun')
            ->assertNotified('Start "Delete all" again');

        $this->assertSame($before, CleanupRun::count(), 'it asks for the typed word and the password again instead');
    }

    #[Test]
    public function the_header_actions_skip_backup_targets_for_an_admin_without_manage_backups(): void
    {
        $this->actingAs($this->cleanupOnlyAdmin(), 'admin');
        Livewire::test(CleanupDashboard::class)->callAction('previewAll')->assertSet('running', true);

        $targets = CleanupRun::latest('id')->first()->items()->pluck('target');
        $this->assertTrue($targets->contains('cron_logs'), 'the targets this admin may run are previewed');
        $backups = collect(app(TargetRegistry::class)->all())->filter(fn ($t) => $t->group() === 'backups')->keys();
        $this->assertSame([], $targets->intersect($backups)->values()->all(), 'no backup target is included');

        // The super admin previews everything, backup targets included.
        CleanupRun::query()->update(['status' => CleanupRun::STATUS_FAILED, 'active_key' => null]);
        $this->actingAs($this->admin, 'admin');
        Livewire::test(CleanupDashboard::class)->callAction('previewAll');
        $all = CleanupRun::latest('id')->first()->items()->pluck('target');
        $this->assertNotEmpty($all->intersect($backups)->all());
    }

    /* ---- settings ---------------------------------------------------- */

    #[Test]
    public function retention_is_saved_audited_and_never_below_the_floor(): void
    {
        $component = Livewire::test(CleanupDashboard::class);

        $component->call('saveRetention', 'search_logs', 45);
        $this->assertSame('45', DB::table('settings')->where(['group' => 'cleanup', 'key' => 'retention_search_logs'])->value('value'));
        $audit = ActivityLog::where('action', 'cleanup.settings_changed')->latest('id')->first();
        $this->assertSame(['from' => 90, 'to' => 45], $audit->new_values['retention_days']);

        // activity_logs: floor 30 — 5 days is refused and the stored value is untouched.
        $component->call('saveRetention', 'activity_logs', 5);
        $this->assertSame(365, (int) DB::table('settings')->where(['group' => 'cleanup', 'key' => 'retention_activity_logs'])->value('value'));

        $component->call('saveRetention', 'search_logs', 'abc');
        $this->assertSame('45', DB::table('settings')->where(['group' => 'cleanup', 'key' => 'retention_search_logs'])->value('value'));
        $component->call('saveRetention', 'not_a_target', 10);
        $component->call('saveRetention', 'dev_files', 10); // not age-based
        $this->assertDatabaseMissing('settings', ['key' => 'retention_not_a_target']);
    }

    #[Test]
    public function the_keep_days_box_in_the_table_saves_through_the_same_rules(): void
    {
        $component = Livewire::test(CleanupDashboard::class);

        $component->call('updateTableColumnState', 'days', 'search_logs', 60);
        $this->assertSame('60', DB::table('settings')->where(['group' => 'cleanup', 'key' => 'retention_search_logs'])->value('value'));

        $component->call('updateTableColumnState', 'days', 'activity_logs', 3); // below the floor of 30
        $this->assertSame(365, (int) DB::table('settings')->where(['group' => 'cleanup', 'key' => 'retention_activity_logs'])->value('value'));
    }

    #[Test]
    public function the_automatic_switch_flips_per_target_and_is_audited(): void
    {
        $this->assertSame('1', DB::table('settings')->where(['group' => 'cleanup', 'key' => 'auto_cron_logs'])->value('value'));

        Livewire::test(CleanupDashboard::class)
            ->call('updateTableColumnState', 'auto', 'cron_logs', false)
            ->call('saveAuto', 'no_such_target', true);

        $this->assertSame('0', DB::table('settings')->where(['group' => 'cleanup', 'key' => 'auto_cron_logs'])->value('value'));
        $this->assertTrue(ActivityLog::where('action', 'cleanup.settings_changed')->exists());
        $this->assertDatabaseMissing('settings', ['key' => 'auto_no_such_target']);
    }

    #[Test]
    public function engine_settings_are_validated_saved_and_audited_with_the_old_and_new_value(): void
    {
        $component = Livewire::test(CleanupDashboard::class)
            ->fillForm(['window_start' => '25:99', 'chunk_rows' => 5])
            ->call('saveEngineSettings')
            ->assertHasFormErrors(['window_start', 'chunk_rows']);

        $component->fillForm(['window_start' => '01:30', 'window_end' => '05:00', 'chunk_rows' => 1000, 'auto_enabled' => true, 'max_delete_pct' => 40, 'history_retention_days' => 90])
            ->call('saveEngineSettings')
            ->assertHasNoFormErrors();

        $this->assertSame('1', DB::table('settings')->where(['group' => 'cleanup', 'key' => 'auto_enabled'])->value('value'));
        $this->assertSame('01:30', settings('cleanup.window_start'));
        $this->assertSame(1000, (int) settings('cleanup.chunk_rows'));

        $audit = ActivityLog::where('action', 'cleanup.settings_changed')->latest('id')->first();
        $this->assertSame(['from' => false, 'to' => true], $audit->new_values['changes']['auto_enabled']);
        $this->assertSame(['from' => '02:00', 'to' => '01:30'], $audit->new_values['changes']['window_start']);
    }

    /* ---- file logs --------------------------------------------------- */

    #[Test]
    public function chosen_log_files_can_be_deleted_or_emptied_from_the_log_files_table(): void
    {
        $old = $this->sandbox.'/logs/old.log';
        file_put_contents($old, str_repeat('x', 2000));
        touch($old, time() - 72 * 3600);
        $active = $this->sandbox.'/logs/active.log';
        file_put_contents($active, str_repeat('y', 3000));

        $component = Livewire::test(CleanupFileLogsTable::class)->assertSee('old.log')->assertSee('active.log');

        $component->callTableBulkAction('deleteLogs', ['old.log']);
        $this->assertFileDoesNotExist($old);

        $component->callTableBulkAction('emptyLogs', ['active.log']);
        clearstatcache();
        $this->assertFileExists($active);
        $this->assertSame(0, filesize($active));
        $this->assertSame(2, ActivityLog::whereIn('action', ['cleanup.file_delete', 'cleanup.file_truncate'])->count());
    }

    #[Test]
    public function an_active_log_file_cannot_be_deleted_only_emptied(): void
    {
        $active = $this->sandbox.'/logs/active.log';
        file_put_contents($active, str_repeat('y', 3000));

        Livewire::test(CleanupFileLogsTable::class)
            ->callTableBulkAction('deleteLogs', ['active.log'])
            ->assertNotified();

        $this->assertFileExists($active, 'a file written to in the last hour is never deleted');
        $this->assertSame(3000, filesize($active));
    }

    /* ---- backups, history, stats, run result ------------------------- */

    #[Test]
    public function the_backups_tab_explains_the_retention_policy(): void
    {
        Livewire::test(CleanupBackupPlanTable::class)
            ->assertSee('Backups on disk')
            ->assertSee('policy');
    }

    #[Test]
    public function the_stats_cards_show_disk_automatic_cleaning_and_the_last_result(): void
    {
        Livewire::test(CleanupStats::class)
            ->assertSee('Disk used')
            ->assertSee('Can be cleaned')
            ->assertSee('Automatic cleaning')
            ->assertSee('Last cleanup')
            ->assertSee('Never');
    }

    #[Test]
    public function the_history_lists_runs_and_can_show_the_details_of_an_old_one(): void
    {
        $this->cronRows(300);
        $component = Livewire::test(CleanupDashboard::class)
            ->callTableAction('clean', 'cron_logs', data: ['confirm' => 'DELETE']);
        $this->pollToEnd($component);
        $first = CleanupRun::latest('id')->first();

        $component->call('dismissRun')->assertSet('runId', null)->assertDontSee('Result per item');

        // The History tab is its own component; its "Details" button tells the page which run to show.
        Livewire::test(CleanupHistoryTable::class)
            ->assertSee('#'.$first->id)
            ->assertSee('Completed')
            ->callTableAction('details', (string) $first->id)
            ->assertDispatched('cleanup-show-run', id: $first->id);

        $component->dispatch('cleanup-show-run', id: $first->id)
            ->assertSet('runId', $first->id)
            ->assertSee('Completed &amp; verified', false)
            ->assertSee('Result per item');
    }

    #[Test]
    public function the_result_table_shows_found_removed_left_and_freed_per_item(): void
    {
        $this->cronRows(300);
        $component = Livewire::test(CleanupDashboard::class)
            ->callTableAction('clean', 'cron_logs', data: ['confirm' => 'DELETE']);
        $this->pollToEnd($component);
        $run = CleanupRun::latest('id')->first();

        Livewire::test(CleanupRunItemsTable::class, ['runId' => $run->id])
            ->assertSee('Scheduled task logs')
            ->assertSee('Completed')
            ->assertSee('Gone elsewhere')
            ->assertSee('300');
    }
}
