<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\Product;
use App\Models\Setting;
use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\ExportEstimator;
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
use App\Services\Migration\MigrationPaths;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.12: no freeze can outlive its deadline, whatever happens to the browser tab, the worker or the
 * process that was driving it. Three things can end it (the watchdog, the next web request, the driver
 * itself), and all three must agree on what ending it means.
 */
class FreezeReleaseTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA';

    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('exportstage');
        config(['backup.staging_disk' => 'exportstage', 'migration.export.batch_seconds' => 0, 'migration.export.page_rows' => 50]);

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-freezerel-'.getmypid().'-'.uniqid();
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

    private function setMaintenance(string $value): void
    {
        Setting::query()->where('group', 'maintenance')->where('key', 'enabled')->delete();
        Setting::create(['group' => 'maintenance', 'key' => 'enabled', 'value' => $value, 'type' => 'boolean', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('maintenance');
    }

    private function maintenanceOn(): bool
    {
        app(SettingsService::class)->forget('maintenance');

        return (bool) app(SettingsService::class)->get('maintenance.enabled', false);
    }

    private function startFrozenRun(ExportOptions $options = new ExportOptions(consistency: 'freeze', hold: false)): BackupRun
    {
        $registry = new GroupRegistry;
        $plan = (new ExportPlanner($registry, new DependencyResolver($registry, new FkGraph), new LogicalRefRegistry($registry), new ExportEstimator))->plan([GroupKey::Catalog], $options);

        $run = app(ExportManager::class)->startExport($plan, self::PASSPHRASE, 'cli');
        app(ExportManager::class)->advance($run); // the freeze engages in the first step
        $run->refresh();

        $this->assertTrue(FreezeState::read()->isLive());
        $this->assertTrue($this->maintenanceOn());

        return $run;
    }

    // ---- the driver stops: the deadline passes ---------------------------------------------------------

    #[Test]
    public function the_next_web_request_after_the_deadline_lifts_the_freeze_and_fails_the_unfinished_export(): void
    {
        $this->setMaintenance('0');
        $run = $this->startFrozenRun();
        $cap = FreezeState::read()->exportDeadlineAt;

        $this->get('/en/')->assertStatus(503); // frozen: the shop shows the maintenance page

        Carbon::setTestNow($cap->addMinute());
        $this->get('/en/'); // any visitor

        $this->assertNull(FreezeState::read());
        $this->assertFalse($this->maintenanceOn(), 'the previous value is back');

        $run->refresh();
        $this->assertSame(BackupRun::STATUS_FAILED, $run->status);
        $this->assertSame('freeze_expired', $run->meta['error_code']);
        $this->assertSame('not_consistent', $run->meta['export_report']['consistency']);
        $this->assertNull(app(KeyEscrow::class)->get('export-'.$run->id));
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    #[Test]
    public function the_watchdog_does_the_same_without_any_web_request(): void
    {
        $this->setMaintenance('0');
        $run = $this->startFrozenRun();

        Carbon::setTestNow(FreezeState::read()->exportDeadlineAt->addMinute());
        Artisan::call('oeparts:migrate:watchdog');

        $this->assertNull(FreezeState::read());
        $this->assertFalse($this->maintenanceOn());
        $this->assertSame('freeze_expired', $run->refresh()->meta['error_code']);
    }

    #[Test]
    public function an_operator_who_had_maintenance_on_before_the_freeze_gets_it_back_on(): void
    {
        $this->setMaintenance('1');
        $this->startFrozenRun();

        Carbon::setTestNow(FreezeState::read()->exportDeadlineAt->addMinute());
        $this->get('/en/');

        $this->assertNull(FreezeState::read());
        $this->assertTrue($this->maintenanceOn(), 'the freeze must not switch off what the operator switched on');
    }

    #[Test]
    public function nothing_is_lifted_before_the_deadline(): void
    {
        $this->setMaintenance('0');
        $run = $this->startFrozenRun();

        Carbon::setTestNow(FreezeState::read()->exportDeadlineAt->subMinute());
        Artisan::call('oeparts:migrate:watchdog');
        $this->get('/en/')->assertStatus(503);

        $this->assertTrue(FreezeState::read()->isLive());
        $this->assertTrue($this->maintenanceOn());
        $this->assertSame(BackupRun::STATUS_RUNNING, $run->refresh()->status);
    }

    #[Test]
    public function the_driver_itself_notices_an_expired_freeze_on_its_next_step(): void
    {
        $this->setMaintenance('0');
        $run = $this->startFrozenRun();

        Carbon::setTestNow(FreezeState::read()->exportDeadlineAt->addMinute());
        $progress = app(ExportManager::class)->advance($run);

        $this->assertSame(BackupRun::STATUS_FAILED, $progress->status);
        $this->assertSame('freeze_expired', $run->refresh()->meta['error_code']);
        $this->assertNull(FreezeState::read());
        $this->assertFalse($this->maintenanceOn());
    }

    // ---- the hold after a sealed package -----------------------------------------------------------------

    #[Test]
    public function after_a_sealed_package_only_the_hold_is_lifted_and_the_package_is_untouched(): void
    {
        $this->setMaintenance('0');
        $registry = new GroupRegistry;
        $planner = new ExportPlanner($registry, new DependencyResolver($registry, new FkGraph), new LogicalRefRegistry($registry), new ExportEstimator);
        $plan = $planner->plan([GroupKey::Catalog], new ExportOptions(consistency: 'freeze', hold: true, holdReleaseMinutes: 30));

        $manager = app(ExportManager::class);
        $run = $manager->startExport($plan, self::PASSPHRASE, 'cli');
        $steps = 0;
        do {
            $progress = $manager->advance($run);
            $run->refresh();
            $this->assertLessThan(1000, ++$steps);
        } while (! $progress->done);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $state = FreezeState::read();
        $this->assertSame(FreezeState::PHASE_HOLD, $state->phase);
        $package = $run->meta['package']['path'];
        $this->assertFileExists($package);

        Carbon::setTestNow($state->currentDeadline()->subMinute());
        $this->get('/en/')->assertStatus(503);
        $this->assertTrue(FreezeState::read()->isLive(), 'still inside the hold');

        Carbon::setTestNow($state->currentDeadline()->addMinute());
        $this->get('/en/');

        $this->assertNull(FreezeState::read());
        $this->assertFalse($this->maintenanceOn());
        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->refresh()->status, 'a finished export is not failed because its hold ran out');
        $this->assertFileExists($package);
    }

    #[Test]
    public function with_no_freeze_the_middleware_costs_nothing_and_the_watchdog_is_quiet(): void
    {
        $this->get('/en/')->assertStatus(200);
        $this->assertSame(0, Artisan::call('oeparts:migrate:watchdog'));
        $this->assertFileExists(MigrationPaths::root().DIRECTORY_SEPARATOR.'watchdog.json', 'the watchdog stamps its tick');
    }
}
