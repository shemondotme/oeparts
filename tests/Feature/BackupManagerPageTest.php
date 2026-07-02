<?php

namespace Tests\Feature;

use App\Filament\Pages\System\BackupDashboard;
use App\Jobs\RestoreBackupJob;
use App\Jobs\RunBackupJob;
use App\Models\Admin;
use App\Models\BackupRun;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);

        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
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
    public function run_now_dispatches_a_backup_job(): void
    {
        Queue::fake();
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(BackupDashboard::class)->callTableAction('runNow');

        Queue::assertPushed(RunBackupJob::class);
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
