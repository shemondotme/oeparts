<?php

namespace Tests\Feature;

use App\Filament\Pages\System\BackupDashboard;
use App\Jobs\RestoreBackupJob;
use App\Models\Admin;
use App\Models\BackupRun;
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
}
