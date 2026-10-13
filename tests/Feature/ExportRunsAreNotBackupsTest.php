<?php

namespace Tests\Feature;

use App\Filament\Pages\System\BackupDashboard;
use App\Models\Admin;
use App\Models\BackupRun;
use App\Services\Backup\BackupJanitor;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupRetentionService;
use App\Services\Backup\Exceptions\BackupException;
use App\Services\Backup\StageRegistry;
use App\Services\HealthCheckService;
use App\Services\Updates\Exceptions\UpdateException;
use App\Services\Updates\ProductionRestoreService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.7: a site-migration export lives in the backup_runs table and uses the backup engine, but it is
 * not a backup. A finished export ends in status `success` like a real backup, so every consumer that asks
 * "is there a good backup?" would believe it. These tests pin the scopes that keep exports out of those
 * questions, so that no caller has to remember to.
 */
class ExportRunsAreNotBackupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, RolesSeeder::class]);
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function run_(string $profile, string $status, array $attributes = []): BackupRun
    {
        return BackupRun::create(array_merge([
            'profile' => $profile, 'status' => $status, 'trigger' => BackupRun::TRIGGER_MANUAL, 'disk' => 'local',
            'started_at' => now()->subHour(), 'finished_at' => $status === BackupRun::STATUS_SUCCESS ? now() : null,
        ], $attributes));
    }

    // ---- scopes ---------------------------------------------------------------------------------

    #[Test]
    public function the_backup_scopes_leave_exports_out_and_the_explicit_scopes_bring_them_back(): void
    {
        $backup = $this->run_(BackupRun::PROFILE_FULL, BackupRun::STATUS_SUCCESS);
        $export = $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_SUCCESS);
        $failedBackup = $this->run_(BackupRun::PROFILE_FULL, BackupRun::STATUS_FAILED);
        $runningExport = $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_RUNNING);

        $ids = fn ($query): array => $query->pluck('id')->sort()->values()->all();

        $this->assertSame([$backup->id], $ids(BackupRun::query()->successful()));
        $this->assertSame([$backup->id, $export->id], $ids(BackupRun::query()->successfulIncludingExports()));
        $this->assertSame([$failedBackup->id], $ids(BackupRun::query()->partials()));
        $this->assertSame([$failedBackup->id, $runningExport->id], $ids(BackupRun::query()->partialsIncludingExports()));
        $this->assertSame([$export->id, $runningExport->id], $ids(BackupRun::query()->exports()));
        $this->assertSame([$backup->id, $failedBackup->id], $ids(BackupRun::query()->notExports()));
    }

    #[Test]
    public function an_export_owns_the_shared_lock_under_its_own_prefix(): void
    {
        $backup = $this->run_(BackupRun::PROFILE_FULL, BackupRun::STATUS_RUNNING);
        $export = $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_RUNNING);

        $this->assertSame('backup:'.$backup->id, $backup->lockOwner());
        $this->assertSame('export:'.$export->id, $export->lockOwner());
    }

    #[Test]
    public function an_export_is_never_restorable_even_when_it_is_successful(): void
    {
        $export = $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_SUCCESS);
        $backup = $this->run_(BackupRun::PROFILE_FULL, BackupRun::STATUS_SUCCESS);

        $this->assertFalse($export->isRestorable());
        $this->assertTrue($backup->isRestorable());
    }

    #[Test]
    public function the_production_restore_refuses_an_export_before_touching_anything(): void
    {
        $export = $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_SUCCESS);

        $this->expectException(UpdateException::class);
        app(ProductionRestoreService::class)->restore($export);
    }

    // ---- the consumers the scopes protect -------------------------------------------------------

    #[Test]
    public function the_health_check_does_not_mistake_a_newer_export_for_the_latest_backup(): void
    {
        $this->run_(BackupRun::PROFILE_FULL, BackupRun::STATUS_SUCCESS, ['finished_at' => now()->subDays(3)]);
        $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_SUCCESS, ['finished_at' => now()]);

        $last = app(HealthCheckService::class)->lastBackupAt();

        $this->assertTrue($last->lt(now()->subDays(2)), 'the three-day-old backup, not the export from a minute ago');
    }

    #[Test]
    public function the_health_check_reports_no_backup_when_only_an_export_exists(): void
    {
        $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_SUCCESS);

        $this->assertNull(app(HealthCheckService::class)->lastBackupAt());
    }

    #[Test]
    public function the_retention_plan_neither_keeps_nor_prunes_an_export(): void
    {
        $backup = $this->run_(BackupRun::PROFILE_FULL, BackupRun::STATUS_SUCCESS);
        $export = $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_SUCCESS, ['finished_at' => now()->subYear()]);

        $plan = app(BackupRetentionService::class)->plan();

        $this->assertArrayHasKey($backup->id, $plan['keep']);
        $this->assertArrayNotHasKey($export->id, $plan['keep']);
        $this->assertNotContains($export->id, $plan['prune']->pluck('id')->all(), 'an old export is not GFS-pruned like a backup');
    }

    #[Test]
    public function the_janitor_does_not_treat_an_idle_export_as_an_abandoned_backup(): void
    {
        $idleExport = $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_RUNNING);
        $idleExport->forceFill(['updated_at' => now()->subHours(5)])->saveQuietly();
        $idleBackup = $this->run_(BackupRun::PROFILE_FULL, BackupRun::STATUS_RUNNING);
        $idleBackup->forceFill(['updated_at' => now()->subHours(5)])->saveQuietly();
        $failedExport = $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_FAILED);

        $ids = app(BackupJanitor::class)->partialsQuery()->pluck('id')->all();

        $this->assertSame([$idleBackup->id], $ids);
        $this->assertNotContains($idleExport->id, $ids);
        $this->assertNotContains($failedExport->id, $ids);
    }

    #[Test]
    public function a_fresh_running_export_does_not_count_as_a_fresh_backup_for_the_lock_guard(): void
    {
        $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_RUNNING);

        $this->assertFalse(BackupRun::hasFreshActiveRun(3600));

        $this->run_(BackupRun::PROFILE_FULL, BackupRun::STATUS_RUNNING);

        $this->assertTrue(BackupRun::hasFreshActiveRun(3600));
    }

    #[Test]
    public function the_backup_page_does_not_adopt_a_running_export_nor_list_it(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $export = $this->run_(BackupRun::PROFILE_EXPORT, BackupRun::STATUS_RUNNING);
        $backup = $this->run_(BackupRun::PROFILE_FULL, BackupRun::STATUS_SUCCESS);

        $page = Livewire::test(BackupDashboard::class)->loadTable();

        $page->assertSet('runningBackupId', null)
            ->assertCanSeeTableRecords([$backup])
            ->assertCanNotSeeTableRecords([$export]);

        $running = $this->run_(BackupRun::PROFILE_FULL, BackupRun::STATUS_RUNNING);
        Livewire::test(BackupDashboard::class)->assertSet('runningBackupId', $running->id);
    }

    // ---- the engine seams -----------------------------------------------------------------------

    private function manager(): BackupManager
    {
        return new class(app(StageRegistry::class), app(BackupLock::class), app(BackupManifest::class)) extends BackupManager
        {
            protected function allowedProfiles(): array
            {
                return [...parent::allowedProfiles(), BackupRun::PROFILE_EXPORT];
            }

            protected function overallPercent(BackupRun $run, array $stages, int $stageIndex, float $stageFraction): int
            {
                return 42;
            }
        };
    }

    #[Test]
    public function the_backup_manager_refuses_to_start_an_export(): void
    {
        $this->expectException(BackupException::class);

        app(BackupManager::class)->start(BackupRun::PROFILE_EXPORT);
    }

    #[Test]
    public function a_subclass_can_start_an_export_and_it_takes_the_lock_under_the_export_prefix(): void
    {
        $manager = $this->manager();
        $lock = app(BackupLock::class);
        $lock->release();

        try {
            $run = $manager->start(BackupRun::PROFILE_EXPORT);

            $this->assertSame(BackupRun::PROFILE_EXPORT, $run->profile);
            $this->assertSame('export:'.$run->id, $lock->owner()['owner']);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function the_progress_weighting_is_overridable_and_a_plain_backup_keeps_equal_weights(): void
    {
        $default = new class(app(StageRegistry::class), app(BackupLock::class), app(BackupManifest::class)) extends BackupManager
        {
            public function percent(BackupRun $run, array $stages, int $index, float $fraction): int
            {
                return $this->overallPercent($run, $stages, $index, $fraction);
            }
        };

        $stages = [new \stdClass, new \stdClass, new \stdClass, new \stdClass];

        $this->assertSame(50, $default->percent(new BackupRun, $stages, 1, 1.0), 'two of four stages done');
        $this->assertSame(38, $default->percent(new BackupRun, $stages, 1, 0.5));
    }

    // ---- the guard: nobody queries the table without choosing a scope ------------------------------

    /**
     * Every list query on BackupRun under app/ must either sit on a scope that already excludes exports
     * (successful, partials, notExports, exports and the two *IncludingExports) or be on this allow-list, each
     * with the reason it is correct to see exports there. A new query that does neither fails the build.
     */
    #[Test]
    public function every_backup_run_list_query_chooses_a_scope_or_is_on_the_reviewed_list(): void
    {
        $allowed = [
            'app/Services/Cleanup/SystemBusyGuard.php' => 'an export IS busy work: nothing may update or clean up while one runs',
            'app/Services/Cleanup/Targets/ConfiguredFileTarget.php' => 'looks one run up by the id in a folder name to decide whether the folder is an orphan; an export is just another id',
        ];
        $safeScope = '/->(successful|partials|notExports|exports|successfulIncludingExports|partialsIncludingExports)\(\)/';

        $offenders = [];

        foreach ($this->phpFilesUnder(base_path('app')) as $path) {
            $relative = str_replace('\\', '/', substr($path, strlen(base_path()) + 1));

            if ($relative === 'app/Models/BackupRun.php') {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $number => $line) {
                if (! preg_match('/BackupRun::(query|where|whereIn|whereNot|all|orderBy|latest|oldest|select|withCount|has|doesntHave)\(/', $line)) {
                    continue;
                }

                $window = implode("\n", array_slice($lines, $number, 8));

                if (preg_match($safeScope, $window) || isset($allowed[$relative])) {
                    continue;
                }

                $offenders[] = $relative.':'.($number + 1).'  '.trim($line);
            }
        }

        $this->assertSame([], $offenders, 'a BackupRun list query that sees exports by accident: use a scope or add it to the reviewed list with a reason');
    }

    /** @return array<int, string> */
    private function phpFilesUnder(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
