<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\Product;
use App\Models\Setting;
use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupProgress;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\Exceptions\BackupException;
use App\Services\Backup\Exceptions\BackupLockException;
use App\Services\Backup\StageStepResult;
use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\ExportEstimator;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\FreezeState;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Export\Stages\ExportFilesStage;
use App\Services\Migration\Export\Stages\ExportPlanStage;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\PackageReader;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.11b: the export engine end to end, on the real stages and the real package writer, with the
 * command-line loop (advance until terminal) as the driver. Live copy and freeze, a fresh process at every
 * step, the shared lock, the failure cleanup, and what a person is and is not allowed to start.
 */
class ExportManagerTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA';

    private string $base = '';

    /** @var array<int, int> */
    private array $runs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('exportstage');
        config(['backup.staging_disk' => 'exportstage', 'migration.export.batch_seconds' => 0, 'migration.export.page_rows' => 50, 'migration.export.batch_files' => 20]);

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-exportmgr-'.getmypid().'-'.uniqid();
        mkdir($this->base.'/public/media', 0775, true);
        mkdir($this->base.'/public/branding', 0775, true);
        mkdir($this->base.'/exports', 0775, true);

        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->base.'/exports'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->base.'/history.ndjson'));
        $this->app->when(ExportFilesStage::class)->needs('$disks')->give(fn (): array => [
            'public' => ['path' => $this->base.'/public', 'prefix' => 'storage/app/public'],
            'local' => ['path' => $this->base.'/local', 'prefix' => 'storage/app/private'],
        ]);

        $this->clean();
    }

    protected function tearDown(): void
    {
        foreach ($this->runs as $id) {
            app(KeyEscrow::class)->delete('export-'.$id);
        }
        $this->clean();
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

    // ---- helpers ---------------------------------------------------------------------------------

    private function seedShop(int $products = 7): void
    {
        Product::factory()->count($products)->create();
        file_put_contents($this->base.'/public/media/photo.jpg', random_bytes(5000));
        file_put_contents($this->base.'/public/media/.env', 'APP_KEY=nope');
        file_put_contents($this->base.'/public/branding/logo.png', 'png');
        Setting::create(['group' => 'maintenance', 'key' => 'enabled', 'value' => '0', 'type' => 'boolean', 'is_encrypted' => false]);
    }

    private function planner(?int $free = null): ExportPlanner
    {
        $registry = new GroupRegistry;

        return new ExportPlanner(
            $registry, new DependencyResolver($registry, new FkGraph), new LogicalRefRegistry($registry), new ExportEstimator,
            $free === null ? null : fn (): int => $free
        );
    }

    /** @param  array<int, GroupKey>  $groups */
    private function plan(array $groups = [GroupKey::Catalog, GroupKey::Media], ExportOptions $options = new ExportOptions): ExportPlan
    {
        return $this->planner()->plan($groups, $options);
    }

    private function manager(): ExportManager
    {
        return app(ExportManager::class);
    }

    /**
     * Drive a run to its end with a FRESH manager (a new process, as far as state goes) for every step.
     *
     * @param  (\Closure(int, BackupRun, BackupProgress): void)|null  $afterStep
     */
    private function drive(BackupRun $run, ?\Closure $afterStep = null): BackupProgress
    {
        $steps = 0;

        do {
            $progress = $this->manager()->advance($run);
            $run->refresh();
            $afterStep?->__invoke($steps, $run, $progress);
            $this->assertLessThan(2000, ++$steps, 'the export never finishes');
        } while (! $progress->done);

        return $progress;
    }

    private function start(ExportPlan $plan, string $via = 'cli'): BackupRun
    {
        $run = $this->manager()->startExport($plan, self::PASSPHRASE, $via);
        $this->runs[] = $run->id;

        return $run;
    }

    // ---- a live copy, end to end -----------------------------------------------------------------------

    #[Test]
    public function a_live_export_produces_a_package_the_real_reader_opens_and_verifies(): void
    {
        $this->seedShop(7);

        $run = $this->start($this->plan());
        $progress = $this->drive($run);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $progress->status, (string) $progress->error);

        $package = $run->meta['package'];
        $reader = PackageReader::open($package['path'], self::PASSPHRASE);
        $reader->verifyAll();

        $this->assertSame($package['fingerprint'], $reader->fingerprint());
        $names = array_column($reader->entries(), 'name');
        $this->assertContains('db/products.0000', $names);
        $this->assertContains('db/settings.0000', $names);
        $this->assertContains('report/export-report.json', $names);
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_starts_with($n, 'files/vol-')));
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_starts_with($n, 'files/index.')));
        $this->assertFalse($reader->hasSecrets());

        $meta = $reader->meta();
        $this->assertContains('catalog', $meta['export']['groups']);
        $this->assertContains('core', $meta['export']['groups']);
        $this->assertSame('live', $meta['export']['consistency']);

        // The products really are in there.
        $entry = collect($reader->entries())->firstWhere('name', 'db/products.0000');
        $lines = explode("\n", trim($reader->readString('db/products.0000')));
        $this->assertCount(1 + 7, $lines, 'a header and seven products');
        $this->assertSame('products', json_decode($lines[0], true)['table']);
        $this->assertSame(7, $entry['rows']);

        // And the report says it was a live copy and that the hostile file stayed behind.
        $report = json_decode($reader->readString('report/export-report.json'), true);
        $this->assertSame('eventual', $report['report']['consistency']);
        $this->assertSame(1, $report['report']['files']['skipped']['counts']['forbidden_name']);
    }

    #[Test]
    public function when_it_ends_nothing_is_left_behind_and_everything_is_recorded(): void
    {
        $this->seedShop(3);

        $run = $this->start($this->plan());
        $this->drive($run);
        $package = $run->meta['package'];

        $this->assertFalse(app(BackupLock::class)->isLocked(), 'the shared lock is released');
        $this->assertNull(app(KeyEscrow::class)->get('export-'.$run->id), 'the keys are gone');
        $this->assertSame([], Storage::disk('exportstage')->allFiles("backups/{$run->id}/export"), 'the staged parts were deleted as they were packed');
        $this->assertFileDoesNotExist($package['path'].'.part');
        $this->assertDirectoryDoesNotExist($package['path'].'.part.ledger');

        $listed = app(PackageOutbox::class)->list();
        $this->assertCount(1, $listed);
        $this->assertSame($package['fingerprint'], $listed[0]['info']['fingerprint']);
        $this->assertSame(['catalog', 'media'], array_values(array_intersect(['catalog', 'media'], $listed[0]['info']['groups'])));

        $history = (string) file_get_contents($this->base.'/history.ndjson');
        $this->assertStringContainsString('"event":"export.started"', $history);
        $this->assertStringContainsString('"event":"export.completed"', $history);
        $this->assertStringNotContainsString(self::PASSPHRASE, $history);

        $fresh = BackupRun::find($run->id);
        $this->assertSame(BackupRun::STATUS_SUCCESS, $fresh->status);
        $this->assertSame($package['size'], $fresh->total_bytes);
        $this->assertFalse($fresh->isRestorable(), 'an export is not a backup');
        $this->assertSame(0, BackupRun::query()->successful()->count());
    }

    #[Test]
    public function the_progress_never_goes_backwards_and_reaches_one_hundred_only_at_the_end(): void
    {
        $this->seedShop(5);

        $run = $this->start($this->plan());
        $percents = [];
        $this->drive($run, function (int $step, BackupRun $run, BackupProgress $p) use (&$percents): void {
            $percents[] = $p->percent;
        });

        $this->assertSame($percents, collect($percents)->sort()->values()->all(), 'monotonic');
        $this->assertSame(100, end($percents));
        $this->assertLessThan(100, $percents[count($percents) - 2] ?? 0);
        $this->assertGreaterThan(10, count($percents), 'a step does a small piece of work');
    }

    #[Test]
    public function an_export_that_is_resumed_by_a_new_process_at_every_step_still_gives_the_same_verified_package(): void
    {
        $this->seedShop(9);

        $steps = 0;
        $run = $this->start($this->plan());
        $this->drive($run, function (int $step, BackupRun $run) use (&$steps): void {
            $steps++;

            // The process "died" in the middle of writing: junk after the committed end of the package being built.
            $part = $run->meta['final_path'].'.part';
            if (is_file($part)) {
                file_put_contents($part, "\x00TORN-AFTER-LAST-COMMIT", FILE_APPEND);
            }
        });

        $reader = PackageReader::open($run->meta['package']['path'], self::PASSPHRASE);
        $reader->verifyAll();
        $this->assertSame(9, collect($reader->entries())->firstWhere('name', 'db/products.0000')['rows']);
        $this->assertGreaterThan(10, $steps);
    }

    // ---- freeze ------------------------------------------------------------------------------------------

    #[Test]
    public function a_freeze_export_holds_the_shop_while_it_copies_and_hands_it_back_exactly_as_it_was(): void
    {
        $this->seedShop(4);
        $settings = app(SettingsService::class);
        $frozenDuringRun = false;

        $run = $this->start($this->plan(options: new ExportOptions(consistency: 'freeze', hold: false)));
        $this->assertFalse((bool) $settings->get('maintenance.enabled', false), 'starting the run froze nothing');

        $progress = $this->drive($run, function (int $step, BackupRun $run) use ($settings, &$frozenDuringRun): void {
            $settings->forget('maintenance');

            if (! $run->isTerminal() && (bool) $settings->get('maintenance.enabled', false)) {
                $frozenDuringRun = true;
            }
        });

        $this->assertSame(BackupRun::STATUS_SUCCESS, $progress->status, (string) $progress->error);
        $this->assertTrue($frozenDuringRun, 'the shop was in maintenance while the copy ran');

        $settings->forget('maintenance');
        $this->assertFalse((bool) $settings->get('maintenance.enabled', false), 'and was handed back');
        $this->assertNull(FreezeState::read());
        $this->assertSame('consistent', $run->meta['export_report']['consistency']);
    }

    #[Test]
    public function a_held_freeze_stays_until_it_is_lifted_and_runs_on_its_own_timer(): void
    {
        $this->seedShop(2);

        $run = $this->start($this->plan(options: new ExportOptions(consistency: 'freeze', hold: true, holdReleaseMinutes: 45)));
        $this->drive($run);

        $state = FreezeState::read();
        $this->assertNotNull($state);
        $this->assertSame(FreezeState::PHASE_HOLD, $state->phase);
        $this->assertTrue($state->belongsToRun($run->id));
        $this->assertTrue($state->isLive());
        $this->assertEqualsWithDelta(now()->addMinutes(45)->getTimestamp(), $state->currentDeadline()->getTimestamp(), 120, 'the hold timer of 45 minutes');
        $this->assertFalse(app(BackupLock::class)->isLocked(), 'the shared lock is free once the package is done: only the shop stays frozen');
    }

    // ---- who may start what ---------------------------------------------------------------------------------

    #[Test]
    public function a_freeze_started_from_a_browser_is_refused_but_a_live_copy_from_a_browser_is_not(): void
    {
        $this->seedShop(1);

        try {
            $this->start($this->plan(options: new ExportOptions(consistency: 'freeze')), 'ui');
            $this->fail('a freeze was started from a browser');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::FreezeNeedsQueueOrCli, $e->errorCode);
        }

        $this->assertSame(0, BackupRun::query()->exports()->count(), 'refused before anything was created');
        $this->assertFalse(app(BackupLock::class)->isLocked());

        $run = $this->start($this->plan(), 'ui');
        $this->assertSame('ui', $run->meta['via']);
    }

    #[Test]
    public function a_weak_passphrase_is_refused_before_anything_is_created(): void
    {
        $this->seedShop(1);

        foreach (['', 'short', 'password1234'] as $weak) {
            try {
                $this->manager()->startExport($this->plan(), $weak);
                $this->fail("accepted '{$weak}'");
            } catch (MigrationException $e) {
                $this->assertContains($e->errorCode, [MigrationErrorCode::PassphraseEmpty, MigrationErrorCode::KdfPassphraseWeak]);
            }
        }

        $this->assertSame(0, BackupRun::query()->exports()->count());
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertSame([], glob($this->base.'/exports/*') ?: []);
    }

    #[Test]
    public function not_enough_disk_refuses_the_start_and_creates_nothing(): void
    {
        $this->seedShop(1);
        $plan = $this->planner(free: 1024)->plan([GroupKey::Catalog]);

        $this->assertFalse($plan->isRunnable());

        try {
            $this->manager()->startExport($plan, self::PASSPHRASE);
            $this->fail('started without disk space');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::PreflightDisk, $e->errorCode);
            $this->assertSame(1024, $e->params['free']);
        }

        $this->assertSame(0, BackupRun::query()->exports()->count());
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    // ---- the shared lock -----------------------------------------------------------------------------------

    #[Test]
    public function a_backup_cannot_start_while_an_export_runs_and_an_export_cannot_start_while_a_backup_does(): void
    {
        $this->seedShop(1);
        $run = $this->start($this->plan());

        try {
            app(BackupManager::class)->start(BackupRun::PROFILE_FULL);
            $this->fail('a backup started on top of an export');
        } catch (BackupLockException) {
            $this->addToAssertionCount(1);
        }

        $this->drive($run);
        $this->assertFalse(app(BackupLock::class)->isLocked());

        $lock = app(BackupLock::class);
        file_put_contents($lock->path(), json_encode(['owner' => 'backup:5', 'acquired_at' => now()->toIso8601String()]));
        $exportsBefore = BackupRun::query()->exports()->count();

        try {
            $this->manager()->startExport($this->plan(), self::PASSPHRASE);
            $this->fail('an export started on top of a backup');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::LockBusy, $e->errorCode);
        }

        $this->assertSame($exportsBefore, BackupRun::query()->exports()->count(), 'the row it made was removed with the refusal');
        $this->assertSame('backup:5', $lock->owner()['owner'], 'the backup keeps its lock');
    }

    #[Test]
    public function a_run_whose_lock_was_taken_by_someone_else_stops_without_writing_anything_more(): void
    {
        $this->seedShop(5);
        $run = $this->start($this->plan(options: new ExportOptions(consistency: 'freeze', hold: false)));
        $this->manager()->advance($run); // the plan stage engages the freeze
        $run->refresh();
        $partsBefore = $run->parts()->count();
        $checkpoint = $run->checkpoint();

        $lock = app(BackupLock::class);
        $lock->release(); // a janitor reclaimed it while the run sat idle ...
        file_put_contents($lock->path(), json_encode(['owner' => 'backup:99', 'acquired_at' => now()->toIso8601String()])); // ... and a backup took it

        $progress = $this->manager()->advance($run);
        $run->refresh();

        $this->assertSame(BackupRun::STATUS_FAILED, $progress->status);
        $this->assertSame('lock_lost', $run->meta['error_code']);
        $this->assertSame($partsBefore, $run->parts()->count());
        $this->assertSame('backup:99', $lock->owner()['owner'], 'the new owner is left alone');
        $this->assertNull(FreezeState::read(), 'a failed export never leaves the shop frozen');
        $this->assertNull(app(KeyEscrow::class)->get('export-'.$run->id));
        $this->assertSame($checkpoint, $run->checkpoint(), 'the run did not advance after it lost the lock');
    }

    #[Test]
    public function a_run_whose_lock_was_merely_released_takes_it_back_and_carries_on(): void
    {
        $this->seedShop(3);
        $run = $this->start($this->plan());
        $this->manager()->advance($run);

        $lock = app(BackupLock::class);
        $lock->release();

        $this->manager()->advance($run);

        $this->assertSame($run->lockOwner(), $lock->owner()['owner']);
        $progress = $this->drive($run);
        $this->assertSame(BackupRun::STATUS_SUCCESS, $progress->status, (string) $progress->error);
    }

    // ---- failure ------------------------------------------------------------------------------------------------

    #[Test]
    public function a_stage_that_fails_ends_the_run_and_cleans_up_everything_it_held(): void
    {
        $this->seedShop(2);
        config(['backup.stages.export' => [ExportPlanStage::class, ExportManagerFailingStage::class]]);

        $run = $this->start($this->plan(options: new ExportOptions(consistency: 'freeze', hold: false)));
        $progress = $this->drive($run);

        $this->assertSame(BackupRun::STATUS_FAILED, $progress->status);
        $this->assertStringContainsString('boom', (string) $progress->error);
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertNull(FreezeState::read(), 'the freeze the plan stage engaged is lifted');
        $this->assertFalse((bool) app(SettingsService::class)->get('maintenance.enabled', false));
        $this->assertNull(app(KeyEscrow::class)->get('export-'.$run->id));
        $this->assertStringContainsString('"event":"export.failed"', (string) file_get_contents($this->base.'/history.ndjson'));
        $this->assertFileDoesNotExist($run->meta['final_path'], 'no half-written package is ever at the final path');
    }

    #[Test]
    public function the_backup_manager_itself_still_refuses_to_start_an_export_profile(): void
    {
        $this->expectException(BackupException::class);

        app(BackupManager::class)->start(BackupRun::PROFILE_EXPORT);
    }
}

/** A stage that fails, to prove the failure cleanup. */
class ExportManagerFailingStage implements BackupStage
{
    public function key(): string
    {
        return 'db';
    }

    public function step(BackupRun $run, array $state): StageStepResult
    {
        throw new \RuntimeException('boom');
    }
}
