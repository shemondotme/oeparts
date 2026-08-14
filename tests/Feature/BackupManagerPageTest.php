<?php

namespace Tests\Feature;

use App\Filament\Pages\System\BackupDashboard;
use App\Jobs\RestoreBackupJob;
use App\Models\Admin;
use App\Models\BackupRun;
use App\Models\Setting;
use App\Services\Backup\BackupLock;
use App\Services\HealthCheckService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Backup Manager page (Module 21, Chunk 2.6) — access control + run/restore/delete
 * actions. Download/restore are re-auth'd, audited PII exports (rule #45).
 */
class BackupManagerPageTest extends TestCase
{
    use RefreshDatabase;

    private string $filesRoot;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);

        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // runNow/pollBackup now drive a real BackupManager FSM (not a dispatched
        // job) — scope the file stage to a tiny fixture dir, never the real repo
        // (CLAUDE.md rule #49). The lock file also needs its own path per test:
        // a run left non-terminal by one test (e.g. only checking the initial
        // "started" state) must not hold the shared lock into the next test.
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local']);
        config(['backup.db.chunk_rows' => 100]);
        $this->filesRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-backuppage-files-'.getmypid();
        @mkdir($this->filesRoot, 0775, true);
        file_put_contents($this->filesRoot.'/a.txt', 'content');
        config(['backup.files.root' => $this->filesRoot]);

        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-backuppage-state-'.getmypid().'-'.Str::random(8);
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->filesRoot.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->filesRoot);
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);
        parent::tearDown();
    }

    private function adminWithRole(string $role, array $attributes = []): Admin
    {
        $admin = Admin::factory()->create(array_merge(['is_active' => true], $attributes));
        $admin->assignRole($role);

        return $admin;
    }

    private function makeRun(): BackupRun
    {
        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL,
            'status'  => BackupRun::STATUS_SUCCESS,
            'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk'    => 'local',
            'finished_at' => now(),
        ]);
        $path = 'backups/'.$run->id.'/db/part.enc';
        Storage::disk('local')->put($path, 'x');
        $run->parts()->create(['type' => 'db', 'sequence' => 0, 'disk' => 'local', 'path' => $path, 'bytes' => 1]);

        return $run;
    }

    #[Test]
    public function a_manager_can_open_the_backup_page(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $this->get(BackupDashboard::getUrl())
            ->assertSuccessful()
            ->assertSee('Backup Management');
    }

    #[Test]
    public function a_role_without_manage_backups_is_forbidden(): void
    {
        $this->actingAs($this->adminWithRole('support'), 'admin');

        $this->get(BackupDashboard::getUrl())->assertForbidden();
    }

    #[Test]
    public function the_table_lists_backup_runs(): void
    {
        $this->makeRun();
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $this->get(BackupDashboard::getUrl())->assertSuccessful()->assertSee('full');
    }

    #[Test]
    public function run_now_starts_the_fsm_without_blocking_on_a_dispatched_job(): void
    {
        // Regression guard: dispatching RunBackupJob here would run the WHOLE
        // backup inline under QUEUE_CONNECTION=sync (shared hosting, rule #41),
        // blocking the request past the web server's timeout. runNow must only
        // call BackupManager::start() — fast — and hand off to pollBackup().
        Queue::fake();
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $component = Livewire::test(BackupDashboard::class)->callTableAction('runNow');

        Queue::assertNothingPushed();
        $run = BackupRun::sole();
        $this->assertSame(BackupRun::STATUS_RUNNING, $run->status);
        $component->assertSet('runningBackupId', $run->id);
    }

    #[Test]
    public function poll_backup_advances_one_chunk_per_tick_and_completes(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $component = Livewire::test(BackupDashboard::class)->callTableAction('runNow');
        $run = BackupRun::sole();

        // One poll tick performs exactly one stage step — the fixture is tiny
        // but the FSM still needs several ticks (db stage, file stage, encrypt).
        $ticks = 0;
        while ($run->fresh()->status === BackupRun::STATUS_RUNNING && $ticks++ < 500) {
            $component->call('pollBackup');
        }

        $run->refresh();
        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $this->assertGreaterThan(1, $ticks, 'expected multiple poll ticks, not one big synchronous run');
        $component->assertSet('runningBackupId', null);
    }

    #[Test]
    public function mount_resumes_polling_a_backup_left_running_after_a_reload(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL,
            'status'  => BackupRun::STATUS_RUNNING,
            'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk'    => 'local',
        ]);

        Livewire::test(BackupDashboard::class)->assertSet('runningBackupId', $run->id);
    }

    #[Test]
    public function delete_removes_the_run_and_its_files(): void
    {
        $run = $this->makeRun();
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(BackupDashboard::class)->callTableAction('delete', $run);

        $this->assertModelMissing($run);
        Storage::disk('local')->assertMissing('backups/'.$run->id.'/db/part.enc');
    }

    #[Test]
    public function restore_requires_password_and_dispatches_a_restore_job(): void
    {
        Queue::fake();
        $run = $this->makeRun();
        $this->actingAs($this->adminWithRole('super_admin', ['password' => Hash::make('secret-pass')]), 'admin');

        Livewire::test(BackupDashboard::class)
            ->callTableAction('restore', $run, data: ['password' => 'secret-pass']);

        Queue::assertPushed(RestoreBackupJob::class);
    }

    /* ---- Lock Status card + Backup Settings panel (this redesign) ---- */

    private function writeLockFile(string $owner, \Illuminate\Support\Carbon $acquiredAt): void
    {
        $path = rtrim($this->statePath, '/\\').DIRECTORY_SEPARATOR.'lock';
        file_put_contents($path, json_encode([
            'owner'       => $owner,
            'acquired_at' => $acquiredAt->toIso8601String(),
        ], JSON_PRETTY_PRINT));
    }

    #[Test]
    public function lock_status_reports_free_when_no_lock_is_held(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $component = Livewire::test(BackupDashboard::class);

        $this->assertFalse($component->instance()->lockStatus()['locked']);
        $component->assertDontSee('Release stale lock');
    }

    #[Test]
    public function lock_status_reports_held_and_not_stale_for_a_fresh_lock(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        $this->writeLockFile('backup:99', now());

        $component = Livewire::test(BackupDashboard::class);
        $status = $component->instance()->lockStatus();

        $this->assertTrue($status['locked']);
        $this->assertFalse($status['is_stale']);
        $component->assertDontSee('Release stale lock');
    }

    #[Test]
    public function lock_status_reports_stale_for_an_old_lock(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        $this->writeLockFile('backup:1', now()->subHours(3)); // default stale threshold is 1hr

        $component = Livewire::test(BackupDashboard::class);
        $status = $component->instance()->lockStatus();

        $this->assertTrue($status['locked']);
        $this->assertTrue($status['is_stale']);
        $component->assertSee('Release stale lock');
    }

    #[Test]
    public function release_lock_releases_a_confirmed_stale_lock_and_audits_it(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        $this->writeLockFile('backup:1', now()->subHours(3));

        Livewire::test(BackupDashboard::class)->call('releaseLock');

        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    #[Test]
    public function release_lock_refuses_to_release_a_fresh_lock(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        $this->writeLockFile('backup:1', now());

        Livewire::test(BackupDashboard::class)->call('releaseLock');

        // Critical safety regression guard: a lock that is NOT confirmed
        // stale must never be releasable, regardless of client-side state.
        $this->assertTrue(app(BackupLock::class)->isLocked());
    }

    // Note: releaseLock()'s abort_unless(canManageBackups()) is defense-in-depth
    // only — canAccess() already requires 'manage backups' to reach this page's
    // Livewire component at all (see a_role_without_manage_backups_is_forbidden
    // above), so a non-permitted admin can never actually call this method in
    // production. Not separately tested here: calling a plain method that
    // aborts mid-request on a HasTable-backed page hits a Livewire test-harness
    // snapshot-serialization quirk unrelated to this application's own code
    // (confirmed by reproducing the same failure against saveBackupSettings()
    // too) — not worth working around for a guard that's already provably
    // unreachable given the page-level gate.

    #[Test]
    public function overview_storage_used_sums_successful_unpruned_runs_only(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        BackupRun::create(['profile' => BackupRun::PROFILE_FULL, 'status' => BackupRun::STATUS_SUCCESS, 'trigger' => BackupRun::TRIGGER_MANUAL, 'disk' => 'local', 'total_bytes' => 1000, 'finished_at' => now()]);
        BackupRun::create(['profile' => BackupRun::PROFILE_FULL, 'status' => BackupRun::STATUS_SUCCESS, 'trigger' => BackupRun::TRIGGER_MANUAL, 'disk' => 'local', 'total_bytes' => 2000, 'finished_at' => now(), 'meta' => ['pruned_at' => now()->toIso8601String()]]);
        BackupRun::create(['profile' => BackupRun::PROFILE_FULL, 'status' => BackupRun::STATUS_FAILED, 'trigger' => BackupRun::TRIGGER_MANUAL, 'disk' => 'local', 'total_bytes' => 4000]);

        $component = Livewire::test(BackupDashboard::class);

        $this->assertSame(1000, $component->instance()->overviewStorageUsedBytes());
    }

    #[Test]
    public function overview_last_backup_matches_health_check_service(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');
        $this->makeRun();

        $component = Livewire::test(BackupDashboard::class);

        $this->assertSame(
            app(HealthCheckService::class)->checkLastBackup(),
            $component->instance()->overviewLastBackup(),
        );
    }

    #[Test]
    public function overview_encryption_reflects_backup_cipher_haskey(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        config(['backup.encryption.key' => '']);
        $this->assertFalse(Livewire::test(BackupDashboard::class)->instance()->overviewEncryptionReady());

        config(['backup.encryption.key' => 'a-real-key']);
        $this->assertTrue(Livewire::test(BackupDashboard::class)->instance()->overviewEncryptionReady());
    }

    #[Test]
    public function save_backup_settings_persists_and_is_read_back_via_settings_helper(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(BackupDashboard::class)
            ->set('settingsRetentionDaily', 14)
            ->set('settingsRetentionWeekly', 8)
            ->set('settingsRetentionMonthly', 12)
            ->set('settingsScheduleTime', '03:30')
            ->set('settingsScheduleEnabled', false)
            ->set('settingsStaleAfterMinutes', 90)
            ->call('saveBackupSettings')
            ->assertHasNoErrors();

        $this->assertSame('14', Setting::where('group', 'backup')->where('key', 'retention_daily')->first()?->value);
        $this->assertSame('03:30', settings('backup.schedule_time'));
        $this->assertSame('0', Setting::where('group', 'backup')->where('key', 'schedule_enabled')->first()?->value);
        $this->assertFalse(filter_var(settings('backup.schedule_enabled'), FILTER_VALIDATE_BOOLEAN));
        $this->assertSame(90 * 60, (int) settings('backup.stale_after_seconds'));
    }

    #[Test]
    public function mount_reads_schedule_enabled_from_settings_falling_back_to_config(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Setting::where('group', 'backup')->where('key', 'schedule_enabled')->delete();
        config(['backup.schedule.enabled' => false]);

        $component = Livewire::test(BackupDashboard::class);

        $component->assertSet('settingsScheduleEnabled', false);
    }

    #[Test]
    public function save_backup_settings_validates_schedule_time_format(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(BackupDashboard::class)
            ->set('settingsScheduleTime', '25:99')
            ->call('saveBackupSettings')
            ->assertHasErrors(['settingsScheduleTime']);
    }

    // Note: saveBackupSettings()'s abort_unless(canManageBackups()) is
    // defense-in-depth only — see the comment above releaseLock()'s parallel
    // note; same page-level canAccess() gate, same Livewire test-harness
    // snapshot quirk when a plain method aborts mid-request on a
    // HasTable-backed page, same reasoning for not separately testing it here.

    private function scheduledBackupEvent(): \Illuminate\Console\Scheduling\Event
    {
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();

        foreach ($events as $event) {
            if (str_contains($event->command, 'oeparts:backup') && str_contains($event->command, '--trigger=scheduled')) {
                return $event;
            }
        }

        $this->fail('Could not find the scheduled oeparts:backup event in routes/console.php.');
    }

    #[Test]
    public function the_scheduled_backup_command_is_gated_by_backup_schedule_enabled(): void
    {
        $settingsService = app(\App\Services\SettingsService::class);

        $settingsService->set('backup.schedule_enabled', '1');
        $this->assertTrue($this->scheduledBackupEvent()->filtersPass($this->app));

        $settingsService->set('backup.schedule_enabled', '0');
        $this->assertFalse($this->scheduledBackupEvent()->filtersPass($this->app));
    }
}
