<?php

namespace Tests\Support;

use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportRollback;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\Stages\DryRunPlanStage;
use App\Services\Migration\Import\Stages\EnsureSchemaStage;
use App\Services\Migration\Import\Stages\LocateStage;
use App\Services\Migration\Import\Stages\PreflightStage;
use App\Services\Migration\Import\Stages\RecoveryPointStage;
use App\Services\Migration\Import\Stages\VerifyStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationPaths;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Ux\PackageUnlocker;
use App\Services\Updates\UpdateChecker;
use Illuminate\Support\Facades\DB;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * Builds a real, valid .oeparts for the import tests (the real writer, a cheap key derivation) that describes THIS
 * test database: the package carries this site's own migration list and release, a usable super administrator, a
 * few tables and a small file list. Tests then change one thing at a time and watch what the engine says.
 *
 * Also takes care of what every such test must: the cheap key derivation bound in the container, the singletons that
 * hold it forgotten, throttle counters and runs cleaned up.
 */
trait BuildsImportPackages
{
    use BuildsPackages;

    /** @var array<int, string> */
    private array $runsBefore = [];

    private function prepareImportTest(): void
    {
        $this->makeWorkDir();
        $this->app->instance(Kdf::class, $this->weakKdf());

        foreach ([ImportManager::class, ImportStageRegistry::class, PackageUnlocker::class, LocateStage::class, VerifyStage::class, PreflightStage::class, DryRunPlanStage::class, EnsureSchemaStage::class, RecoveryPointStage::class, ImportRollback::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        $this->clearThrottle();
        $this->runsBefore = array_map(fn (ImportState $s): string => $s->id, (new ImportStateStore)->all());
    }

    private function cleanUpImportTest(): void
    {
        foreach ((new ImportStateStore)->all() as $state) {
            if (! in_array($state->id, $this->runsBefore, true)) {
                app(KeyEscrow::class)->delete('import-'.$state->id);
                exec('rm -rf '.escapeshellarg((new ImportStateStore)->directory($state->id)));
            }
        }

        $this->clearThrottle();
        $this->removeWorkDir();
    }

    private function clearThrottle(): void
    {
        foreach (glob(MigrationPaths::throttle().DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function manager(): ImportManager
    {
        return app(ImportManager::class);
    }

    private function store(): ImportStateStore
    {
        return new ImportStateStore;
    }

    /** @return array<int, string> the migrations this test database has run */
    private function ownMigrations(): array
    {
        $names = DB::table('migrations')->pluck('migration')->map(fn ($n): string => (string) $n)->all();
        sort($names);

        return $names;
    }

    private function ownVersion(): ?string
    {
        $version = app(UpdateChecker::class)->currentVersion();

        return $version === 'unknown' ? null : $version;
    }

    /**
     * @param  array<string, mixed>  $metaOverride  merged over the package's meta (migrations, app_version, php_version, ...)
     * @param  array<int, array<string, mixed>>|null  $admins  rows of the admins table (null = one usable super administrator)
     */
    private function buildImportPackage(string $name = 'site.oeparts', array $metaOverride = [], ?array $admins = null): string
    {
        $meta = array_replace([
            'source' => ['domain' => 'old.example'],
            'app_version' => $this->ownVersion(),
            'php_version' => PHP_VERSION,
            'migrations' => $this->ownMigrations(),
            'export' => ['groups' => ['core', 'catalog', 'media']],
        ], $metaOverride);

        $admins ??= [['id' => 1, 'name' => 'Root', 'email' => 'root@old.example', 'password' => '$2y$04$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'is_active' => 1, 'role' => 'super_admin']];

        $writer = $this->newWriter($name, $meta);

        $columns = ['id', 'name', 'email', 'password', 'is_active', 'role'];
        $adminText = json_encode(['$' => 'oe-rows/1', 'table' => 'admins', 'columns' => $columns], JSON_UNESCAPED_SLASHES)."\n";
        foreach ($admins as $row) {
            $adminText .= json_encode(array_map(fn (string $c) => $row[$c] ?? null, $columns), JSON_UNESCAPED_SLASHES)."\n";
        }
        $writer->addEntryFromString('db/admins.0000', 'db', $adminText, ['group' => 'core', 'rows' => count($admins), 'attrs' => ['table' => 'admins']]);
        $writer->addEntryFromString('db/settings.0000', 'db', "{\"\$\":\"oe-rows/1\",\"table\":\"settings\"}\n[1,\"a\"]\n", ['group' => 'core', 'rows' => 1, 'attrs' => ['table' => 'settings']]);
        $writer->addEntryFromString('db/products.0000', 'db', "{\"\$\":\"oe-rows/1\",\"table\":\"products\"}\n[1,\"p\"]\n", ['group' => 'catalog', 'rows' => 1, 'attrs' => ['table' => 'products']]);
        $writer->addEntryFromString('files/index.000', 'files-index', "{\"d\":\"public\",\"p\":\"media/a.jpg\",\"s\":1,\"m\":1}\n", ['group' => 'media', 'rows' => 1, 'codec' => 'none']);
        $writer->seal();

        return $this->packagePath($name);
    }

    /**
     * Start a run and bring it to the point where it waits for confirmation.
     *
     * @param  array<string, mixed>  $options
     * @return array{state: ImportState, token: string, progress: array<string, mixed>}
     */
    private function runToPreflight(string $path, array $options = [], string $kind = 'import'): array
    {
        $result = $this->manager()->start(['path' => $path], $this->passphrase, ['email' => 'tester@example.test'], $kind, 'cli', $options);
        $progress = $this->manager()->advance($result['state']->id, 60.0);

        return ['state' => $this->store()->find($result['state']->id), 'token' => $result['token'], 'progress' => $progress];
    }

    /** @return array<string, array<string, mixed>> the stored preflight checks keyed by check key */
    private function checksOf(ImportState $state): array
    {
        $checks = [];

        foreach ((array) ($state->package['preflight']['checks'] ?? []) as $check) {
            $checks[$check['key']] = $check;
        }

        return $checks;
    }
}
