<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\Product;
use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Install\InstallManager;
use App\Services\Instance\ConfigReloader;
use App\Services\Instance\Contracts\InstanceBegin;
use App\Services\Instance\InstanceEnv;
use App\Services\Instance\InstanceState;
use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\ExportEstimator;
use App\Services\Migration\Export\ExportFilesStage;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportRollback;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\RowLoader;
use App\Services\Migration\Import\Stages\ArmStage;
use App\Services\Migration\Import\Stages\DbAuditStage;
use App\Services\Migration\Import\Stages\DbClearStage;
use App\Services\Migration\Import\Stages\DbDataStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsImportPackages;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 6.6: closing the site and loading the data. The arm stage (seven sub-steps, each undoable), the clearing of the
 * old tables, the batch-by-batch load of a REAL package made by the real export engine, and the check after it.
 */
class ImportArmAndDataTest extends TestCase
{
    use BuildsImportPackages;
    use PlantsRows;
    use RefreshDatabase;

    private string $envFile = '';

    private string $envText = '';

    private object $guard;

    private string $ledgerBackup = '';

    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareImportTest();
        app(BackupLock::class)->release();
        (new ImportMarker)->remove();
        config(['migration.import.recovery_max_rows' => 500_000, 'queue.default' => 'sync']);

        $ledger = InstanceState::file()->path();
        $this->ledgerBackup = is_file($ledger) ? (string) file_get_contents($ledger) : '';
        @unlink($ledger);
        @unlink($ledger.'.prev');

        $this->envText = "APP_NAME=OeParts\nAPP_KEY=base64:SECRET\nAPP_URL=http://old.local\nOE_MODE=\nMAIL_HOST=smtp.real.example\n";
        $this->envFile = $this->workDir.'/.env';
        file_put_contents($this->envFile, $this->envText);
        $path = $this->envFile;
        $installer = new class($path) extends InstallManager
        {
            public function __construct(private readonly string $path) {}

            protected function envPath(): string
            {
                return $this->path;
            }
        };
        $this->app->instance(InstanceEnv::class, new InstanceEnv($installer, new ConfigReloader, new InstanceState, $path));

        $this->guard = new class implements QueueGuard
        {
            public int $held = 0;

            public int $resumed = 0;

            public ?\Closure $onHold = null;

            public function hold(): void
            {
                $this->held++;

                if ($this->onHold !== null) {
                    ($this->onHold)($this->held);
                }
            }

            public function resume(): void
            {
                $this->resumed++;
            }
        };
        $this->app->instance(QueueGuard::class, $this->guard);

        Storage::fake('exportstage');
        config(['backup.staging_disk' => 'exportstage', 'migration.export.batch_seconds' => 0, 'migration.export.page_rows' => 50, 'migration.export.batch_files' => 20]);
        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-armdata-'.getmypid().'-'.uniqid();
        mkdir($this->base.'/public/media', 0775, true);
        mkdir($this->base.'/exports', 0775, true);
        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->base.'/exports'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->base.'/history.ndjson'));
        $this->app->when(ExportFilesStage::class)->needs('$disks')->give(fn (): array => [
            'public' => ['path' => $this->base.'/public', 'prefix' => 'storage/app/public'],
            'local' => ['path' => $this->base.'/local', 'prefix' => 'storage/app/private'],
        ]);

        $this->rebuildImportServices();
    }

    protected function tearDown(): void
    {
        app(BackupLock::class)->release();
        (new ImportMarker)->remove();
        $ledger = InstanceState::file()->path();
        @unlink($ledger);
        @unlink($ledger.'.prev');
        if ($this->ledgerBackup !== '') {
            file_put_contents($ledger, $this->ledgerBackup);
        }
        exec('rm -rf '.escapeshellarg($this->base));
        $this->cleanUpImportTest();

        parent::tearDown();
    }

    private function rebuildImportServices(): void
    {
        foreach ([ImportManager::class, ImportStageRegistry::class, ImportRollback::class, ArmStage::class, DbClearStage::class, DbDataStage::class, DbAuditStage::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    private function plantSite(int $redirects = 3): void
    {
        $this->plantRow('admins', ['name' => 'Root', 'email' => 'root@site.test', 'password' => '$2y$04$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'is_active' => 1, 'role' => 'super_admin']);
        Product::factory()->count(5)->create();
        $rows = [];
        foreach (range(1, $redirects) as $i) {
            $rows[] = ['from_url' => "/from-{$i}", 'to_url' => "/to-{$i}", 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            $this->plantRowsRaw('redirects', $chunk);
        }
    }

    /** @param  array<int, array<string, mixed>>  $rows  bulk insert with the filler values a single planted row gets */
    private function plantRowsRaw(string $table, array $rows): void
    {
        $id = $this->plantRow($table, ['from_url' => '/template', 'to_url' => '/template']);
        $template = (array) DB::table($table)->where('id', $id)->first();
        DB::table($table)->where('id', $id)->delete();
        unset($template['id'], $template['from_url'], $template['to_url']);

        DB::table($table)->insert(array_map(fn (array $r): array => $r + $template, $rows));
    }

    /** @return array<string, string> table => JSON of every row, for every non-system table */
    private function picture(): array
    {
        $picture = [];

        foreach ((new GroupRegistry)->exportableTables() as $table) {
            $rows = DB::table($table)->get()->map(fn ($r): array => (array) $r)->all();
            usort($rows, fn (array $a, array $b): int => strcmp(json_encode($a), json_encode($b)));
            $picture[$table] = json_encode($rows, JSON_UNESCAPED_UNICODE);
        }

        return $picture;
    }

    /** @param  array<int, GroupKey>  $groups */
    private function exportPackage(array $groups = [GroupKey::Catalog, GroupKey::Content]): string
    {
        $registry = new GroupRegistry;
        $planner = new ExportPlanner($registry, new DependencyResolver($registry, new FkGraph), new LogicalRefRegistry($registry), new ExportEstimator);
        $manager = app(ExportManager::class);
        $run = $manager->startExport($planner->plan($groups, new ExportOptions), $this->passphrase, 'cli');

        for ($i = 0; $i < 2000; $i++) {
            $progress = $manager->advance($run);
            if ($progress->done) {
                break;
            }
        }

        $run->refresh();
        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status, 'the export succeeded');
        app(KeyEscrow::class)->delete('export-'.$run->id);
        app(BackupLock::class)->release();

        return (string) $run->meta['package']['path'];
    }

    /** @return array{0: string, 1: array<string, string>} the package and the picture of the site it holds */
    private function packageOfThisSite(int $redirects = 3, array $groups = [GroupKey::Catalog, GroupKey::Content]): array
    {
        $this->plantSite($redirects);
        $picture = $this->picture();

        return [$this->exportPackage($groups), $picture];
    }

    /** Start the run and bring it through confirmation; leaves it ready to advance into the arm stage. */
    private function confirmedRun(string $package, array $options = []): string
    {
        $run = $this->runToPreflight($package, $options + ['door' => 'cli']);
        $id = $run['state']->id;
        $this->assertSame('awaiting_confirmation', $run['progress']['status'], json_encode(array_map(fn (array $c): string => $c['key'].':'.($c['status'] ?? '').':'.($c['message'] ?? ''), array_filter($this->checksOf($run['state']), fn (array $c): bool => ($c['status'] ?? 'pass') !== 'pass'))));
        $this->assertTrue($this->store()->find($id)->package['preflight']['can_proceed'], json_encode(array_filter($this->checksOf($this->store()->find($id)), fn (array $c): bool => ($c['status'] ?? $c['level'] ?? 'pass') !== 'pass')));
        $acks = (array) $this->store()->find($id)->package['preflight']['required_acks'];
        $this->manager()->confirm($id, $acks, ['door' => 'cli', 'typed' => 'REPLACE', 'replace_populated' => true]);

        return $id;
    }

    /** Advance with a fresh manager each step until the given flag is set or the run ends. */
    private function advanceUntil(string $id, string $flag, float $budget = 0.0, int $max = 4000): array
    {
        $progress = [];

        for ($i = 0; $i < $max; $i++) {
            $this->rebuildImportServices();
            $progress = $this->manager()->advance($id, $budget);

            if ($this->store()->find($id)->flag($flag) || in_array($progress['status'], ['failed', 'rolled_back', 'rollback_failed'], true)) {
                return $progress;
            }
        }

        $this->fail("never reached {$flag}");
    }

    private function context(): ImportContext
    {
        return new ImportContext(microtime(true) + 60, app(KeyEscrow::class));
    }

    // ---- the arm stage ---------------------------------------------------------------------------------------------

    #[Test]
    public function arming_closes_the_site_in_seven_recorded_steps_and_doing_it_again_changes_nothing(): void
    {
        [$package] = $this->packageOfThisSite();
        $id = $this->confirmedRun($package);

        $this->advanceUntil($id, 'arm.repause', 0.0);
        $state = $this->store()->find($id);

        foreach (['arm.lock', 'arm.identity', 'arm.audit', 'arm.marker', 'arm.pause', 'arm.begin', 'arm.repause'] as $flag) {
            $this->assertTrue($state->flag($flag), $flag);
        }
        $this->assertTrue((new ImportMarker)->isActive());
        $this->assertTrue(app(BackupLock::class)->isLocked());
        $this->assertSame(2, $this->guard->held, 'held before the prefix changes and once more after');
        $this->assertFalse($state->isDestructive(), 'arming changes the environment only, never data');

        $started = array_filter(app(PackageAudit::class)->history($id), fn (array $l): bool => $l['event'] === 'import.started');
        $this->assertCount(1, $started);

        // The stage run again (a new process after a crash after the last flag) does nothing more.
        $this->rebuildImportServices();
        $result = app(ArmStage::class)->step($this->store()->find($id), $this->context());
        $this->assertSame(2, $this->guard->held);
        $this->assertTrue($result->done);
        $this->assertCount(1, array_filter(app(PackageAudit::class)->history($id), fn (array $l): bool => $l['event'] === 'import.started'));
    }

    #[Test]
    public function arming_waits_without_touching_anything_while_another_job_holds_the_lock(): void
    {
        [$package] = $this->packageOfThisSite();
        $id = $this->confirmedRun($package);
        app(BackupLock::class)->release();
        app(BackupLock::class)->acquire('backup:other');

        for ($i = 0; $i < 3; $i++) {
            $this->rebuildImportServices();
            $progress = $this->manager()->advance($id, 60.0);
        }

        $this->assertNotSame('failed', $progress['status']);
        $this->assertFalse((new ImportMarker)->isActive(), 'nothing was changed while waiting');
        $this->assertSame(0, $this->guard->held);
        $this->assertSame($this->envText, file_get_contents($this->envFile));

        app(BackupLock::class)->release();
        $this->advanceUntil($id, 'arm.repause', 0.0);
        $this->assertTrue((new ImportMarker)->isActive());
    }

    #[Test]
    public function a_package_that_changed_after_it_was_checked_stops_the_run_and_undoes_the_arming(): void
    {
        [$package] = $this->packageOfThisSite();
        $id = $this->confirmedRun($package);
        file_put_contents($package, 'x', FILE_APPEND);

        $progress = $this->advanceUntil($id, 'arm.repause', 60.0);

        $this->assertSame('failed', $progress['status']);
        $this->assertFalse((new ImportMarker)->isActive());
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertFalse($this->store()->find($id)->isDestructive());
        $this->assertSame($this->envText, file_get_contents($this->envFile));
    }

    #[Test]
    public function a_site_that_changed_since_the_way_back_was_made_stops_the_run_before_anything_is_touched(): void
    {
        [$package] = $this->packageOfThisSite();
        $id = $this->confirmedRun($package);
        $this->advanceUntil($id, 'recovery.done', 0.0);

        $this->plantRow('redirects', ['from_url' => '/late', 'to_url' => '/late']);
        $before = $this->picture();

        $progress = $this->advanceUntil($id, 'arm.repause', 60.0);

        $this->assertSame('failed', $progress['status']);
        $this->assertSame(MigrationErrorCode::NotReadyToConfirm->value, $progress['error']['code']);
        $this->assertSame($before, $this->picture(), 'not a row was touched');
        $this->assertFalse((new ImportMarker)->isActive());
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    #[Test]
    public function a_failure_part_way_through_arming_undoes_every_finished_part_in_reverse(): void
    {
        [$package] = $this->packageOfThisSite();
        $id = $this->confirmedRun($package);

        // begin() writes the instance keys; the second hold() then fails.
        $env = app(InstanceEnv::class);
        $this->app->instance(InstanceBegin::class, new class($env) implements InstanceBegin
        {
            public function __construct(private readonly InstanceEnv $env) {}

            public function begin(ImportState $state): void
            {
                $this->env->apply(['OE_MODE' => 'quarantine', 'OE_INSTANCE_ID' => 'imp1', 'APP_URL' => 'https://new.example'], 'import');
            }
        });
        $this->guard->onHold = function (int $n): void {
            if ($n === 2) {
                throw new \RuntimeException('the second pause failed');
            }
        };

        $progress = $this->advanceUntil($id, 'arm.repause', 60.0);

        $this->assertSame('failed', $progress['status']);
        $state = $this->store()->find($id);
        $this->assertFalse($state->isDestructive(), 'it ends failed, never rolled_back');
        $this->assertSame($this->envText, file_get_contents($this->envFile), 'the .env is exactly as before');
        $this->assertFalse((new ImportMarker)->isActive());
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertSame(1, $this->guard->resumed);
        foreach (['undo.env', 'undo.queue', 'undo.marker', 'undo.lock'] as $flag) {
            $this->assertTrue($state->flag($flag), $flag);
        }
    }

    // ---- clearing -------------------------------------------------------------------------------------------------

    #[Test]
    public function the_old_data_is_never_cleared_while_the_site_is_open(): void
    {
        $this->plantSite();
        $before = $this->picture();
        $state = new ImportState('unarmed-'.uniqid());

        try {
            app(DbClearStage::class)->step($state, $this->context());
            $this->fail('expected a refusal');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::ImportFailedStep, $e->errorCode);
        }

        $this->assertSame($before, $this->picture());
        $this->assertFalse($state->isDestructive());
    }

    #[Test]
    public function the_run_is_marked_destructive_and_saved_before_the_first_table_is_emptied(): void
    {
        [$package] = $this->packageOfThisSite();
        $id = $this->confirmedRun($package);
        $seen = ['destructive' => null];

        $this->app->instance(RowLoader::class, new class($seen, $id) extends RowLoader
        {
            public function __construct(private array &$seen, private readonly string $id)
            {
                parent::__construct();
            }

            public function truncate(string $table): void
            {
                $this->seen['destructive'] ??= (new ImportStateStore)->find($this->id)->isDestructive();
                parent::truncate($table);
            }
        });

        $this->advanceUntil($id, 'clear.done', 60.0);

        $this->assertTrue($seen['destructive'], 'the destructive mark was already on disk when the first TRUNCATE ran');
    }

    // ---- loading ---------------------------------------------------------------------------------------------------

    #[Test]
    public function a_real_package_is_loaded_back_exactly_and_the_audit_passes(): void
    {
        [$package, $picture] = $this->packageOfThisSite(3);
        // The site drifts after the export: the import must put the packaged picture back.
        DB::table('redirects')->delete();
        $this->plantRow('redirects', ['from_url' => '/garbage', 'to_url' => '/garbage']);
        Product::query()->limit(2)->delete();
        $id = $this->confirmedRun($package);

        $progress = $this->advanceUntil($id, 'audit.done', 120.0);

        $this->assertNotContains($progress['status'], ['failed', 'rolled_back', 'rollback_failed'], json_encode($progress['error'] ?? null));
        $state = $this->store()->find($id);
        $this->assertTrue($state->flag('data.done'));
        $this->assertTrue($state->isDestructive());

        foreach (['products', 'redirects', 'settings', 'admins'] as $table) {
            $this->assertSame($picture[$table], $this->picture()[$table], "{$table} equals the package, row for row");
        }
        $this->assertSame(5, $state->package['loaded']['products']);
        $this->assertSame(3, $state->package['loaded']['redirects']);
        $this->assertArrayHasKey('orphans', $state->package['audit']);
    }

    #[Test]
    public function only_the_selected_groups_are_loaded_and_the_others_stay_empty(): void
    {
        [$package, $picture] = $this->packageOfThisSite(3);
        $id = $this->confirmedRun($package, ['groups' => ['core', 'content']]);

        $this->advanceUntil($id, 'audit.done', 120.0);

        $this->assertSame($picture['redirects'], $this->picture()['redirects'], 'content was loaded');
        $this->assertSame('[]', $this->picture()['products'], 'catalog was not selected: it stays empty');
    }

    #[Test]
    public function parents_are_loaded_before_the_rows_that_point_at_them(): void
    {
        [$package] = $this->packageOfThisSite();
        $id = $this->confirmedRun($package);
        $order = [];

        DB::listen(function ($query) use (&$order): void {
            if (preg_match('/^insert into [`"]?(\w+)[`"]?/i', $query->sql, $m) === 1 && ! in_array($m[1], $order, true)) {
                $order[] = $m[1];
            }
        });

        $this->advanceUntil($id, 'data.done', 120.0);

        $position = array_flip($order);
        $this->assertArrayHasKey('products', $position);
        foreach (['manufacturers', 'conditions'] as $parent) {
            if (isset($position[$parent])) {
                $this->assertLessThan($position['products'], $position[$parent], "{$parent} before products");
            }
        }
    }

    #[Test]
    public function a_load_stopped_after_every_batch_by_a_fresh_process_gives_the_same_result(): void
    {
        [$package, $picture] = $this->packageOfThisSite(1300);
        $id = $this->confirmedRun($package);

        $steps = 0;
        do {
            $this->rebuildImportServices();
            $this->manager()->advance($id, 0.0);
            $steps++;
        } while (! $this->store()->find($id)->flag('audit.done') && $steps < 4000);

        $this->assertGreaterThan(10, $steps, 'it really ran in many small steps');
        $this->assertSame($picture['redirects'], $this->picture()['redirects']);
        $this->assertSame(1300, DB::table('redirects')->count());
        $this->assertSame(1300, $this->store()->find($id)->package['loaded']['redirects']);
    }

    #[Test]
    public function a_batch_that_went_in_just_before_a_kill_is_not_inserted_twice(): void
    {
        [$package, $picture] = $this->packageOfThisSite(1300);
        $source = DB::table('redirects')->orderBy('id')->get()->map(fn ($r): array => (array) $r)->all();
        $id = $this->confirmedRun($package);

        // Step until the first 500 redirects are in; then do what a killed process leaves behind: the NEXT 500 inserted too.
        for ($i = 0; $i < 4000 && DB::table('redirects')->count() !== 500; $i++) {
            $this->rebuildImportServices();
            $this->manager()->advance($id, 0.0);
        }
        $this->assertSame(500, DB::table('redirects')->count());
        DB::table('redirects')->insert(array_slice($source, 500, 500));

        $this->advanceUntil($id, 'audit.done', 0.0);

        $this->assertSame(1300, DB::table('redirects')->count(), 'no duplicate');
        $this->assertSame($picture['redirects'], $this->picture()['redirects']);
    }

    #[Test]
    public function a_table_with_a_stray_row_in_the_middle_of_its_load_is_emptied_and_loaded_again(): void
    {
        [$package, $picture] = $this->packageOfThisSite(1300);
        $id = $this->confirmedRun($package);

        for ($i = 0; $i < 4000 && DB::table('redirects')->count() !== 500; $i++) {
            $this->rebuildImportServices();
            $this->manager()->advance($id, 0.0);
        }
        $this->assertSame(500, DB::table('redirects')->count());
        $this->plantRow('redirects', ['from_url' => '/stray', 'to_url' => '/stray']);

        $this->advanceUntil($id, 'audit.done', 0.0);

        $this->assertSame(1300, DB::table('redirects')->count());
        $this->assertSame($picture['redirects'], $this->picture()['redirects'], 'the stray row is gone');
    }

    #[Test]
    public function a_failure_during_the_load_rolls_the_whole_site_back_to_exactly_what_it_was(): void
    {
        [$package] = $this->packageOfThisSite(3);
        // After the export the site gets different content: this is the picture the rollback must restore. Its products point
        // at parents that are not there: the tests run inside a transaction, where SQLite cannot switch foreign keys off, so a
        // parent's TRUNCATE would cascade into children the rollback has already put back (MySQL, where the checks are off, does not).
        $this->deferForeignKeys();
        DB::table('products')->update(['manufacturer_id' => 9999, 'condition_id' => 9999]);
        DB::table('manufacturers')->delete();
        DB::table('conditions')->delete();
        $this->plantRow('redirects', ['from_url' => '/only-here', 'to_url' => '/only-here']);
        $before = $this->picture();
        $id = $this->confirmedRun($package);

        $loader = new class extends RowLoader
        {
            public bool $armed = true;

            public function insert(string $table, array $rows): void
            {
                if ($this->armed && $table === 'redirects') {
                    throw new \RuntimeException('disk full');
                }
                parent::insert($table, $rows);
            }
        };
        $this->app->instance(RowLoader::class, $loader);

        // Advance until the failure sends the run into its rollback, then end the disk problem and let the rollback finish.
        $this->rebuildImportServices(); // the manager and its stages must be made with the failing loader
        for ($i = 0; $i < 200 && ($progress = $this->manager()->advance($id, 120.0))['status'] === 'running'; $i++) {
        }
        $this->assertSame('rolling_back', $progress['status'], 'the failed insert sent the run into its rollback');
        $loader->armed = false;
        for ($i = 0; $i < 200 && ($progress = $this->manager()->advance($id, 120.0))['status'] === 'rolling_back'; $i++) {
        }

        $this->assertSame('rolled_back', $progress['status']);
        $this->assertSame($before, $this->picture(), 'every row of every table is back');
        $this->assertSame($this->envText, file_get_contents($this->envFile));
        $this->assertFalse((new ImportMarker)->isActive());
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertGreaterThanOrEqual(1, $this->guard->resumed);
    }

    // ---- the audit -------------------------------------------------------------------------------------------------

    #[Test]
    public function the_audit_fails_the_run_when_a_table_does_not_hold_what_the_package_says(): void
    {
        [$package] = $this->packageOfThisSite(3);
        $id = $this->confirmedRun($package);
        $this->advanceUntil($id, 'data.done', 0.0);

        DB::table('redirects')->limit(1)->delete(); // something removed a row between the load and the check

        $progress = $this->advanceUntil($id, 'audit.done', 120.0);

        $this->assertContains($progress['status'], ['failed', 'rolled_back', 'rolling_back']);
        $this->assertFalse($this->store()->find($id)->flag('audit.done'));
    }

    #[Test]
    public function orphans_are_reported_as_a_warning_with_counts_and_never_fail_the_run(): void
    {
        [$package] = $this->packageOfThisSite(3);
        $id = $this->confirmedRun($package);
        $this->advanceUntil($id, 'data.done', 0.0);

        // A product that points at a manufacturer which is not there; the count still agrees with the package.
        $this->deferForeignKeys();
        DB::table('products')->limit(1)->update(['manufacturer_id' => 987654]);

        $progress = $this->advanceUntil($id, 'audit.done', 120.0);

        $this->assertNotContains($progress['status'], ['failed', 'rolled_back', 'rollback_failed']);
        $orphans = $this->store()->find($id)->package['audit']['orphans'];
        $this->assertNotEmpty($orphans);
        $this->assertContains(1, array_values($orphans));
    }
}
