<?php

namespace Tests\Feature;

use App\Filament\Pages\System\BackupDashboard;
use App\Jobs\RunProductionRestoreJob;
use App\Models\Admin;
use App\Models\BackupRun;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BackupDashboard::restoreFullAction() — the destructive files+DB production
 * restore, distinct from the existing files-only restoreAction() (asserted
 * in BackupManagerPageTest). Same permission ('restore backups'), same
 * password re-auth pattern; dispatches RunProductionRestoreJob rather than
 * touching the live install synchronously in the request.
 */
class BackupDashboardRestoreFullActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SettingsSeeder::class,
            RolesSeeder::class,
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

    private function makeRestorableRun(): BackupRun
    {
        return BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL,
            'status' => BackupRun::STATUS_SUCCESS,
            'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk' => 'local',
            'finished_at' => now(),
        ]);
    }

    #[Test]
    public function it_is_hidden_without_the_restore_backups_permission(): void
    {
        $run = $this->makeRestorableRun();
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->givePermissionTo('manage backups'); // page access, but not the restore permission
        $this->actingAs($admin, 'admin');

        Livewire::test(BackupDashboard::class)
            ->loadTable()
            ->assertTableActionHidden('restoreFull', $run);
    }

    #[Test]
    public function it_is_hidden_for_a_non_restorable_run(): void
    {
        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL, 'status' => BackupRun::STATUS_FAILED, 'trigger' => BackupRun::TRIGGER_MANUAL,
        ]);
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(BackupDashboard::class)
            ->loadTable()
            ->assertTableActionHidden('restoreFull', $run);
    }

    /**
     * Pre-Phase-22 backlog sweep (2026-09-23). isRestorable() alone doesn't
     * guarantee a backup has both database AND file parts — only PROFILE_FULL
     * does. Restoring a files_only backup through this action used to
     * silently skip the database entirely while still reporting SUCCESS
     * (RestoreManager::restoreDatabase() treats "no DB parts" as a warning,
     * not an error, and ProductionRestoreService::restore() only ever
     * checked errors) — the most dangerous possible outcome for a disaster-
     * recovery feature: an admin believing a full rollback happened when it
     * didn't. Fixed at both the service layer (ProductionRestoreService)
     * and here in the UI, so the action is never even offered.
     */
    #[Test]
    public function it_is_hidden_for_a_files_only_backup(): void
    {
        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_FILES_ONLY,
            'status' => BackupRun::STATUS_SUCCESS,
            'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk' => 'local',
            'finished_at' => now(),
        ]);
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(BackupDashboard::class)
            ->loadTable()
            ->assertTableActionHidden('restoreFull', $run);
    }

    #[Test]
    public function it_is_hidden_for_a_database_only_backup(): void
    {
        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_DATABASE_ONLY,
            'status' => BackupRun::STATUS_SUCCESS,
            'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk' => 'local',
            'finished_at' => now(),
        ]);
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(BackupDashboard::class)
            ->loadTable()
            ->assertTableActionHidden('restoreFull', $run);
    }

    #[Test]
    public function the_wrong_password_does_not_dispatch_the_restore_job(): void
    {
        Queue::fake();
        $run = $this->makeRestorableRun();
        $this->actingAs($this->adminWithRole('super_admin', ['password' => Hash::make('correct-horse')]), 'admin');

        try {
            Livewire::test(BackupDashboard::class)
                ->callTableAction('restoreFull', $run, data: ['password' => 'wrong']);
        } catch (ValidationException) {
            // reauthenticate() throws — the action's own re-auth check, same
            // as the existing files-only restoreAction()'s password gate.
        }

        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_correct_password_dispatches_the_restore_job(): void
    {
        Queue::fake();
        $run = $this->makeRestorableRun();
        $admin = $this->adminWithRole('super_admin', ['password' => Hash::make('correct-horse')]);
        $this->actingAs($admin, 'admin');

        Livewire::test(BackupDashboard::class)
            ->callTableAction('restoreFull', $run, data: ['password' => 'correct-horse']);

        Queue::assertPushed(RunProductionRestoreJob::class, fn ($job) => $job->runId === $run->getKey() && $job->requestedBy === $admin->id
        );
    }
}
