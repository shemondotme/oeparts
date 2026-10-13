<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\Product;
use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\ExportEstimator;
use App\Services\Migration\Export\ExportJanitor;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\FreezeState;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Export\Stages\ExportFilesStage;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.12: the janitor closes what nobody is driving any more, and only that. A run with a live freeze
 * belongs to the freeze's own deadline; a finished package is not an abandoned one.
 */
class ExportJanitorTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA';

    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('exportstage');
        config(['backup.staging_disk' => 'exportstage', 'migration.export.batch_seconds' => 0, 'migration.export.page_rows' => 50]);

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-janitor-'.getmypid().'-'.uniqid();
        mkdir($this->base.'/public/media', 0775, true);
        mkdir($this->base.'/exports', 0775, true);
        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->base.'/exports'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->base.'/history.ndjson'));
        $this->app->when(ExportFilesStage::class)->needs('$disks')->give(fn (): array => ['public' => ['path' => $this->base.'/public', 'prefix' => 'storage/app/public'], 'local' => ['path' => $this->base.'/local', 'prefix' => 'storage/app/private']]);

        $this->clean();
        Product::factory()->count(3)->create();
    }

    protected function tearDown(): void
    {
        BackupRun::query()->exports()->get()->each(fn ($r) => app(KeyEscrow::class)->delete('export-'.$r->id));
        $this->clean();
        Carbon::setTestNow();
        exec('rm -rf '.escapeshellarg($this->base));

        parent::tearDown();
    }

    private function clean(): void
    {
        app(BackupLock::class)->release();
        $path = FreezeState::file()->path();
        foreach ([$path, $path.'.prev', $path.'.lock'] as $file) {
            @unlink($file);
        }
    }

    private function janitor(): ExportJanitor
    {
        return app(ExportJanitor::class);
    }

    private function start(string $consistency): BackupRun
    {
        $registry = new GroupRegistry;
        $plan = (new ExportPlanner($registry, new DependencyResolver($registry, new FkGraph), new LogicalRefRegistry($registry), new ExportEstimator))
            ->plan([GroupKey::Catalog], new ExportOptions(consistency: $consistency, hold: false));

        return app(ExportManager::class)->startExport($plan, self::PASSPHRASE, 'cli');
    }

    /** Make the run look as if nothing had touched it for `$seconds`. */
    private function age(BackupRun $run, int $seconds): void
    {
        BackupRun::query()->whereKey($run->getKey())->update(['updated_at' => now()->subSeconds($seconds)]);
    }

    #[Test]
    public function a_live_run_nobody_has_advanced_for_the_horizon_is_closed_with_everything_it_held(): void
    {
        $run = $this->start('live');
        app(ExportManager::class)->advance($run);
        $run->refresh();

        $this->assertTrue(app(BackupLock::class)->isLocked());
        $this->assertNotNull(app(KeyEscrow::class)->get('export-'.$run->id));

        $this->age($run, 1801);

        $this->assertSame(1, $this->janitor()->failDeadRuns());

        $run->refresh();
        $this->assertSame(BackupRun::STATUS_FAILED, $run->status);
        $this->assertSame('run_stale', $run->meta['error_code']);
        $this->assertFalse(app(BackupLock::class)->isLocked(), 'the shared lock is let go');
        $this->assertNull(app(KeyEscrow::class)->get('export-'.$run->id));
        $this->assertSame([], Storage::disk('exportstage')->allFiles('backups/'.$run->id));
        $this->assertSame([], glob($this->base.'/exports/*.part*') ?: [], 'no package under construction is left');
    }

    #[Test]
    public function a_run_that_moved_recently_is_left_alone(): void
    {
        $run = $this->start('live');
        app(ExportManager::class)->advance($run);

        $this->age($run, 600);

        $this->assertSame(0, $this->janitor()->failDeadRuns());
        $this->assertNotSame(BackupRun::STATUS_FAILED, $run->refresh()->status);
        $this->assertTrue(app(BackupLock::class)->isLocked());
    }

    #[Test]
    public function a_run_with_a_live_freeze_belongs_to_the_freeze_not_the_janitor(): void
    {
        $run = $this->start('freeze');
        app(ExportManager::class)->advance($run);
        $this->assertTrue(FreezeState::read()->isLive());

        $this->age($run, 7200);

        $this->assertSame(0, $this->janitor()->failDeadRuns());
        $this->assertSame(0, $this->janitor()->failUnpickedRuns());
        $this->assertNotSame(BackupRun::STATUS_FAILED, $run->refresh()->status);
        $this->assertTrue(FreezeState::read()->isLive());
    }

    #[Test]
    public function a_run_whose_driver_never_started_is_failed_as_no_worker(): void
    {
        $run = $this->start('live');
        BackupRun::query()->whereKey($run->getKey())->update(['status' => BackupRun::STATUS_PENDING]);
        $this->age($run, 121);

        $this->assertSame(1, $this->janitor()->failUnpickedRuns());

        $run->refresh();
        $this->assertSame(BackupRun::STATUS_FAILED, $run->status);
        $this->assertSame(MigrationErrorCode::NoWorker->value, $run->meta['error_code']);
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertNull(app(KeyEscrow::class)->get('export-'.$run->id));
    }

    #[Test]
    public function a_pending_run_that_did_take_a_step_is_not_called_unpicked(): void
    {
        $run = $this->start('live');
        app(ExportManager::class)->advance($run);
        BackupRun::query()->whereKey($run->getKey())->update(['status' => BackupRun::STATUS_PENDING]);
        $this->age($run, 121);

        $this->assertSame(0, $this->janitor()->failUnpickedRuns());
    }

    #[Test]
    public function a_ledger_without_its_package_in_progress_is_removed_and_one_with_it_is_kept(): void
    {
        $orphan = $this->base.'/exports/gone-20260101-000000.oeparts.part.ledger';
        $kept = $this->base.'/exports/busy-20260101-000000.oeparts.part.ledger';
        foreach ([$orphan, $kept] as $dir) {
            mkdir($dir, 0775, true);
            file_put_contents($dir.'/state.json', '{}');
        }
        file_put_contents($this->base.'/exports/busy-20260101-000000.oeparts.part', 'x');

        $this->assertSame(1, $this->janitor()->removeOrphans());
        $this->assertDirectoryDoesNotExist($orphan);
        $this->assertDirectoryExists($kept);
    }

    #[Test]
    public function finished_packages_past_the_retention_are_deleted_and_fresh_ones_kept(): void
    {
        config(['migration.retention.export_days' => 7]);
        $old = $this->base.'/exports/old-20260101-000000.oeparts';
        $new = $this->base.'/exports/new-20260101-000000.oeparts';
        file_put_contents($old, 'x');
        file_put_contents($new, 'x');
        touch($old, now()->subDays(8)->getTimestamp());

        $this->assertSame(1, $this->janitor()->pruneExpired());
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($new);
    }

    #[Test]
    public function the_command_reports_and_runs_clean_on_an_idle_installation(): void
    {
        $this->assertSame(0, Artisan::call('oeparts:migrate:janitor'));
        $this->assertStringContainsString('0 dead export(s) closed', Artisan::output());
    }
}
