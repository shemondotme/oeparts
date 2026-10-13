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
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\FreezeState;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Export\MigrationQueueProbeJob;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Export\RunExportJob;
use App\Services\Migration\Export\Stages\ExportFilesStage;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\PackageReader;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.12: who drives an export, and what happens when the driver is missing, dead or slow. The
 * queue-driven path is exercised with a REAL worker loop on the database queue driver, because the whole
 * point of the dedicated migration queue is a property of how a real worker treats paused queues.
 */
class ExportDriversTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA';

    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('exportstage');
        config([
            'backup.staging_disk' => 'exportstage', 'migration.export.batch_seconds' => 0, 'migration.export.page_rows' => 50,
            'migration.export.redispatch_delay_seconds' => 0, 'queue.default' => 'database',
        ]);

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-drivers-'.getmypid().'-'.uniqid();
        mkdir($this->base.'/public/media', 0775, true);
        mkdir($this->base.'/exports', 0775, true);
        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->base.'/exports'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->base.'/history.ndjson'));
        $this->app->when(ExportFilesStage::class)->needs('$disks')->give(fn (): array => ['public' => ['path' => $this->base.'/public', 'prefix' => 'storage/app/public'], 'local' => ['path' => $this->base.'/local', 'prefix' => 'storage/app/private']]);

        $this->clean();
        Product::factory()->count(4)->create();
        Setting::create(['group' => 'maintenance', 'key' => 'enabled', 'value' => '0', 'type' => 'boolean', 'is_encrypted' => false]);
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
        foreach ([FreezeState::file()->path(), MigrationQueueProbeJob::stampPath()] as $path) {
            foreach ([$path, $path.'.prev', $path.'.lock'] as $file) {
                @unlink($file);
            }
        }
    }

    // ---- helpers ---------------------------------------------------------------------------------

    private function plan(ExportOptions $options = new ExportOptions): ExportPlan
    {
        $registry = new GroupRegistry;

        return (new ExportPlanner($registry, new DependencyResolver($registry, new FkGraph), new LogicalRefRegistry($registry), new ExportEstimator))->plan([GroupKey::Catalog], $options);
    }

    private function freezePlan(): ExportPlan
    {
        return $this->plan(new ExportOptions(consistency: 'freeze', hold: false));
    }

    private function provenWorker(): void
    {
        MigrationQueueProbeJob::file()->write(['at' => now()->toIso8601String(), 'pid' => 1, 'queue' => 'migration', 'connection' => 'database']);
    }

    private function maintenanceOn(): bool
    {
        app(SettingsService::class)->forget('maintenance');

        return (bool) app(SettingsService::class)->get('maintenance.enabled', false);
    }

    /** One real worker pass over a queue list (the database driver, a real Worker, the cache that holds the pauses). */
    private function work(string $queues): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--queue' => $queues, '--sleep' => 0]);
    }

    // ---- who may start a freeze from a browser ----------------------------------------------------------------

    #[Test]
    public function a_freeze_from_the_admin_page_is_refused_on_the_sync_queue(): void
    {
        config(['queue.default' => 'sync']);

        try {
            app(ExportManager::class)->startExport($this->freezePlan(), self::PASSPHRASE, 'ui');
            $this->fail('accepted');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::FreezeNeedsQueueOrCli, $e->errorCode);
        }

        $this->assertSame(0, BackupRun::query()->exports()->count());
    }

    #[Test]
    public function a_freeze_from_the_admin_page_is_refused_on_a_real_queue_with_no_proven_worker_and_accepted_from_the_cli(): void
    {
        try {
            app(ExportManager::class)->startExport($this->freezePlan(), self::PASSPHRASE, 'ui');
            $this->fail('accepted without a proven worker');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::NoWorker, $e->errorCode);
        }

        $this->assertSame(0, BackupRun::query()->exports()->count());
        $this->assertFalse(app(BackupLock::class)->isLocked());

        $run = app(ExportManager::class)->startExport($this->freezePlan(), self::PASSPHRASE, 'cli');
        $this->assertSame('cli', $run->meta['via'], 'the command line needs no worker at all');
    }

    #[Test]
    public function with_a_proven_worker_the_admin_freeze_is_accepted_pending_and_queued_on_the_migration_queue(): void
    {
        $this->provenWorker();

        $run = app(ExportManager::class)->startExport($this->freezePlan(), self::PASSPHRASE, 'ui');

        $this->assertSame(BackupRun::STATUS_PENDING, $run->refresh()->status);
        $this->assertNull(FreezeState::read(), 'the freeze waits for the driver\'s first step');
        $this->assertFalse($this->maintenanceOn());
        $this->assertSame(1, DB::table('jobs')->where('queue', 'migration')->count());
        $this->assertSame(RunExportJob::class, json_decode((string) DB::table('jobs')->value('payload'), true)['displayName']);
    }

    // ---- the job: one step, then back on the queue -------------------------------------------------------------------

    #[Test]
    public function the_job_takes_one_step_and_puts_itself_back_until_the_run_ends(): void
    {
        $run = app(ExportManager::class)->startExport($this->plan(), self::PASSPHRASE, 'cli');
        $steps = 0;

        while (true) {
            $before = DB::table('jobs')->count();
            (new RunExportJob($run->id))->handle(app(ExportManager::class));
            $run->refresh();

            if ($run->isTerminal()) {
                $this->assertSame($before, DB::table('jobs')->count(), 'a finished run is not queued again');
                break;
            }

            $this->assertSame($before + 1, DB::table('jobs')->count(), 'one more job: itself, for the next step');
            $this->assertLessThan(500, ++$steps);
        }

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $this->assertGreaterThan(5, $steps, 'a step is small');
        DB::table('jobs')->delete();

        (new RunExportJob($run->id))->handle(app(ExportManager::class));
        $this->assertSame(0, DB::table('jobs')->count(), 'a job for a finished run does nothing');
    }

    #[Test]
    public function a_job_that_crashes_fails_the_run_and_lifts_its_freeze(): void
    {
        $run = app(ExportManager::class)->startExport($this->freezePlan(), self::PASSPHRASE, 'cli');
        app(ExportManager::class)->advance($run);
        $this->assertTrue(FreezeState::read()->isLive());

        (new RunExportJob($run->id))->failed(new \RuntimeException('worker killed'));

        $this->assertSame(BackupRun::STATUS_FAILED, $run->refresh()->status);
        $this->assertNull(FreezeState::read());
        $this->assertFalse($this->maintenanceOn());
    }

    // ---- the real worker loop (5.5.1) ----------------------------------------------------------------------------------

    /**
     * The customer queues are paused while the shop is frozen: a customer job waits, the export job still runs on the
     * migration queue, and when the freeze ends the customer job runs.
     */
    #[Test]
    public function a_frozen_shop_keeps_customer_jobs_waiting_while_the_export_runs_through_a_real_worker(): void
    {
        foreach (['migration,critical,default,low' => 'a mixed worker', 'migration' => 'a dedicated migration worker'] as $line => $name) {
            $this->clean();
            DB::table('jobs')->delete();
            Cache::flush();
            BackupRun::query()->delete();
            Setting::query()->where('group', 'maintenance')->update(['value' => '0']);

            $this->provenWorker();
            CustomerJobProbe::reset();
            CustomerJobProbe::dispatch()->onQueue('default');

            $run = app(ExportManager::class)->startExport($this->freezePlan(), self::PASSPHRASE, 'ui');
            $this->runs[] = $run->id;

            $guard = 0;
            $sawFrozen = false;
            while (! $run->refresh()->isTerminal()) {
                $this->work($line);
                $sawFrozen = $sawFrozen || FreezeState::read() !== null;

                if (FreezeState::read() !== null) {
                    $this->assertFalse(CustomerJobProbe::$ran, "{$name}: a customer job ran while the shop was frozen");
                }

                $this->assertLessThan(800, ++$guard, "{$name}: the export never finished");
            }

            $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status, "{$name}: ".$run->error);
            $this->assertTrue($sawFrozen, "{$name}: the shop really was frozen while the worker drove the export");
            $this->assertNull(FreezeState::read(), "{$name}: and was released");
            $this->assertFalse(CustomerJobProbe::$ran, "{$name}: the customer job is still waiting on the queue");
            $this->assertSame(1, DB::table('jobs')->where('queue', 'default')->count());

            if ($line === 'migration') {
                $this->assertSame(1, DB::table('jobs')->count(), 'a dedicated migration worker never touches the customer queue');

                continue;
            }

            $this->work($line); // the freeze is over: the paused job runs
            $this->assertTrue(CustomerJobProbe::$ran, "{$name}: the customer job runs after the release");
        }

        $package = collect(app(PackageOutbox::class)->list())->first();
        PackageReader::open($package['path'], self::PASSPHRASE)->verifyAll();
    }

    /** @var array<int, int> */
    private array $runs = [];

    #[Test]
    public function an_old_worker_line_never_drives_the_export_and_the_run_is_failed_as_no_worker_without_ever_freezing_the_shop(): void
    {
        $this->provenWorker();
        $settingBefore = Setting::query()->where('group', 'maintenance')->where('key', 'enabled')->first()->updated_at;

        $run = app(ExportManager::class)->startExport($this->freezePlan(), self::PASSPHRASE, 'ui');
        $this->runs[] = $run->id;

        for ($i = 0; $i < 5; $i++) {
            $this->work('critical,default,low'); // an old line: it never looks at the migration queue
        }

        $this->assertSame(BackupRun::STATUS_PENDING, $run->refresh()->status);
        $this->assertNull(FreezeState::read(), 'the freeze engages in the first step, which never came');
        $this->assertFalse($this->maintenanceOn());

        Carbon::setTestNow(now()->addSeconds(121));
        Artisan::call('oeparts:migrate:watchdog');

        $run->refresh();
        $this->assertSame(BackupRun::STATUS_FAILED, $run->status);
        $this->assertSame('no_worker', $run->meta['error_code']);
        $this->assertNull(FreezeState::read());
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertNull(app(KeyEscrow::class)->get('export-'.$run->id));
        $this->assertEquals($settingBefore, Setting::query()->where('group', 'maintenance')->where('key', 'enabled')->first()->updated_at, 'the maintenance setting was never even written');
    }

    // ---- an undriven live copy (5.1 rule c) ----------------------------------------------------------------------------

    #[Test]
    public function a_live_copy_whose_tab_was_closed_is_carried_to_the_end_by_the_watchdog_alone(): void
    {
        $run = app(ExportManager::class)->startExport($this->plan(), self::PASSPHRASE, 'ui');
        $this->runs[] = $run->id;

        Carbon::setTestNow(now()->addSeconds(130)); // longer than driver_idle_seconds, far below the 30-minute horizon
        Artisan::call('oeparts:migrate:watchdog');

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->refresh()->status, (string) $run->error.' (one visit must carry the whole export, not one step)');
        PackageReader::open($run->meta['package']['path'], self::PASSPHRASE)->verifyAll();
    }

    #[Test]
    public function the_watchdogs_time_budget_bounds_one_visit_and_the_next_visit_carries_on(): void
    {
        config(['migration.export.watchdog_budget_seconds' => 0]);

        $run = app(ExportManager::class)->startExport($this->plan(), self::PASSPHRASE, 'ui');
        $this->runs[] = $run->id;

        Carbon::setTestNow(now()->addSeconds(130));
        Artisan::call('oeparts:migrate:watchdog');
        $this->assertFalse($run->refresh()->isTerminal(), 'a zero budget allows one step per visit');
        $this->assertSame(BackupRun::STATUS_RUNNING, $run->status);

        $ticks = 0;
        while (! $run->refresh()->isTerminal()) {
            Carbon::setTestNow(now()->addSeconds(130));
            Artisan::call('oeparts:migrate:watchdog');
            $this->assertLessThan(600, ++$ticks);
        }

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
    }

    #[Test]
    public function with_no_scheduler_at_all_a_silent_run_is_closed_after_the_horizon_and_its_lock_is_freed(): void
    {
        $run = app(ExportManager::class)->startExport($this->plan(), self::PASSPHRASE, 'ui');
        $this->runs[] = $run->id;
        app(ExportManager::class)->advance($run);

        Carbon::setTestNow(now()->addMinutes(31));
        Artisan::call('oeparts:migrate:janitor');

        $run->refresh();
        $this->assertSame(BackupRun::STATUS_FAILED, $run->status);
        $this->assertSame('run_stale', $run->meta['error_code']);
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertNull(app(KeyEscrow::class)->get('export-'.$run->id));
        $this->assertSame([], Storage::disk('exportstage')->allFiles('backups/'.$run->id));
    }
}

/** A job on a customer queue, to see whether the worker ran it. */
class CustomerJobProbe implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public static bool $ran = false;

    public static function reset(): void
    {
        self::$ran = false;
    }

    public function handle(): void
    {
        self::$ran = true;
    }
}
