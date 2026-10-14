<?php

namespace Tests\Support;

use App\Models\BackupRun;
use App\Models\Product;
use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Install\InstallManager;
use App\Services\Instance\ConfigReloader;
use App\Services\Instance\InstanceEnv;
use App\Services\Instance\InstanceState;
use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\ExportEstimator;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Export\Stages\ExportFilesStage;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\Files\FilesRollbackHook;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportRollback;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\Stages\ArmStage;
use App\Services\Migration\Import\Stages\CompleteStage;
use App\Services\Migration\Import\Stages\DbAuditStage;
use App\Services\Migration\Import\Stages\DbClearStage;
use App\Services\Migration\Import\Stages\DbDataStage;
use App\Services\Migration\Import\Stages\DbIndexDropStage;
use App\Services\Migration\Import\Stages\DbIndexRestoreStage;
use App\Services\Migration\Import\Stages\DbSeedDefaultsStage;
use App\Services\Migration\Import\Stages\FilesStage;
use App\Services\Migration\Import\Stages\FinAdminsStage;
use App\Services\Migration\Import\Stages\FinBackupKeyStage;
use App\Services\Migration\Import\Stages\FinCachesStage;
use App\Services\Migration\Import\Stages\FinCheckMigrationsStage;
use App\Services\Migration\Import\Stages\FinLinksStage;
use App\Services\Migration\Import\Stages\FinNormalizeStage;
use App\Services\Migration\Import\Stages\FinOriginResetStage;
use App\Services\Migration\Import\Stages\FinQueueStage;
use App\Services\Migration\Import\Stages\FinReadinessStage;
use App\Services\Migration\Import\Stages\FinRewriteStage;
use App\Services\Migration\Import\Stages\FinSecretsStage;
use App\Services\Migration\Import\Stages\FinSequencesStage;
use App\Services\Migration\Import\Stages\FinSessionsStage;
use App\Services\Migration\Import\Stages\FinSitemapStage;
use App\Services\Migration\KeyEscrow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Everything a test needs to drive a REAL import: a temporary .env the instance code may rewrite, a recording queue guard,
 * export disks pointing at a temporary folder, a real package exported from the test database by the real export engine,
 * and helpers to confirm a run and advance it stage by stage with a fresh manager each step.
 */
trait DrivesRealImports
{
    use BuildsImportPackages;
    use PlantsRows;

    private string $envFile = '';

    private string $envText = '';

    private object $guard;

    private string $ledgerBackup = '';

    private string $base = '';

    private function prepareRealImport(): void
    {
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

        // What an import WRITES goes to a separate folder from what the export READS, so the two sites stay apart.
        mkdir($this->base.'/target/public', 0775, true);
        mkdir($this->base.'/target/local', 0775, true);
        $targetDisks = fn (): array => [
            'public' => ['path' => $this->base.'/target/public', 'prefix' => 'storage/app/public'],
            'local' => ['path' => $this->base.'/target/local', 'prefix' => 'storage/app/private'],
        ];
        $this->app->bind('migration.import_disks', $targetDisks);
        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->base.'/exports'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->base.'/history.ndjson'));
        $this->app->when(ExportFilesStage::class)->needs('$disks')->give(fn (): array => [
            'public' => ['path' => $this->base.'/public', 'prefix' => 'storage/app/public'],
            'local' => ['path' => $this->base.'/local', 'prefix' => 'storage/app/private'],
        ]);

        $this->rebuildImportServices();
    }

    private function cleanUpRealImport(): void
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
    }

    private function rebuildImportServices(): void
    {
        foreach ([
            ImportManager::class, ImportStageRegistry::class, ImportRollback::class, ArmStage::class, DbClearStage::class, DbDataStage::class,
            DbAuditStage::class, DbIndexDropStage::class, DbIndexRestoreStage::class, DbSeedDefaultsStage::class, FilesStage::class, FilesRollbackHook::class,
            FinSecretsStage::class, FinCheckMigrationsStage::class, FinRewriteStage::class, FinNormalizeStage::class, FinOriginResetStage::class,
            FinSequencesStage::class, FinCachesStage::class, FinAdminsStage::class, FinSessionsStage::class, FinLinksStage::class, FinSitemapStage::class,
            FinQueueStage::class, FinBackupKeyStage::class, FinReadinessStage::class, CompleteStage::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    private function plantSite(int $redirects = 3): void
    {
        $this->plantRow('languages', ['code' => 'en', 'is_active' => 1, 'is_default' => 1]);
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

        // A busy machine can need more than one budget to get through the checks: keep asking until they are done.
        for ($i = 0; $i < 20 && $run['progress']['status'] === 'running'; $i++) {
            $run['progress'] = $this->manager()->advance($id, 60.0);
            $run['state'] = $this->store()->find($id);
        }

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
}
