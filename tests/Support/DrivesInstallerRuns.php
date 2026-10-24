<?php

namespace Tests\Support;

use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportRollback;
use App\Services\Migration\Import\ImportRunMutex;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\Stages\EnsureSchemaStage;
use App\Services\Migration\Import\Stages\LocateStage;
use App\Services\Migration\Import\Stages\PreflightStage;
use App\Services\Migration\Import\Stages\RecoveryPointStage;
use App\Services\Migration\Import\Stages\VerifyStage;
use App\Services\Updates\UpdateChecker;
use Illuminate\Support\Facades\DB;

/**
 * For tests that take the installer's import branch all the way into the runner: a package the REAL engine accepts (it describes
 * this test database: its migration list, its release, a usable super administrator), the installer's pages driven over HTTP, and
 * the runner's calls driven with the run's own token.
 *
 * Everything that holds state is temporary: the wizard file, the inbox, the key escrow, the wrong-passphrase counters and the
 * RUN STATE (bound into the container before the engine is built). The engine is stopped at the confirmation, so nothing here
 * closes the site, writes `.env` or touches storage/app/migration.
 *
 * The class using it must extend a TestCase that uses RefreshDatabase and call prepareInstallerRunTest() from setUp() and
 * cleanUpInstallerRunTest() from tearDown().
 */
trait DrivesInstallerRuns
{
    use OpensInstallerPackages;

    /** The groups a package may say it carries (everything a person can choose). */
    private const ALL_GROUPS = ['core', 'catalog', 'catalog_files', 'media', 'content', 'customers', 'sales', 'sales_files', 'invoicing', 'shipping_tax', 'marketing', 'admin_prefs', 'carts', 'logs'];

    private function prepareInstallerRunTest(): void
    {
        $this->prepareOpenedPackageTest();

        mkdir($this->tmp.DIRECTORY_SEPARATOR.'runs', 0700, true);

        // The two files that other processes (a rehearsal, another test) take on the REAL site: the backup lock, which the preflight reads,
        // and the import marker, which turns every page into a 503. A test of the installer must neither wait for them nor set them.
        config(['updates.state_path' => $this->tmp.DIRECTORY_SEPARATOR.'updates']);
        $this->app->instance(ImportMarker::class, new ImportMarker($this->tmp.DIRECTORY_SEPARATOR.'importing.json'));
        $this->app->instance(ImportStateStore::class, new ImportStateStore($this->tmp.DIRECTORY_SEPARATOR.'runs'));

        foreach ([ImportManager::class, ImportRunMutex::class, ImportStageRegistry::class, ImportRollback::class, LocateStage::class, VerifyStage::class, PreflightStage::class, EnsureSchemaStage::class, RecoveryPointStage::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    private function cleanUpInstallerRunTest(): void
    {
        $this->cleanUpOpenedPackageTest();
    }

    private function runStore(): ImportStateStore
    {
        return app(ImportStateStore::class);
    }

    /** @return list<string> the ids of every run in the temporary store */
    private function runIds(): array
    {
        return array_values(array_map(static fn (ImportState $s): string => $s->id, $this->runStore()->all()));
    }

    /**
     * A finished package the engine's own checks accept for THIS database.
     *
     * @param  list<string>  $groups  what the package says it carries (core is always one of them)
     * @param  array<string, mixed>  $meta  merged over the package's meta (app_version, migrations, ...)
     */
    private function buildRunnablePackage(string $name = 'shop.oeparts', array $groups = ['core', 'catalog', 'catalog_files', 'media'], array $meta = []): string
    {
        $version = app(UpdateChecker::class)->currentVersion();
        $migrations = DB::table('migrations')->pluck('migration')->map(static fn ($n): string => (string) $n)->all();
        sort($migrations);

        $meta = array_replace([
            'source' => ['domain' => 'old.example'],
            'created_at' => '2026-10-01T10:00:00+00:00',
            'app_version' => $version === 'unknown' ? null : $version,
            'php_version' => PHP_VERSION,
            'migrations' => $migrations,
            'export' => ['preset' => 'full_live', 'anonymised' => false, 'secrets_included' => false],
        ], $meta);
        $meta['export'] = array_replace((array) $meta['export'], ['groups' => $groups]);

        $writer = $this->newWriter($name, $meta);

        $columns = ['id', 'name', 'email', 'password', 'is_active', 'role'];
        $admin = [1, 'Root', 'root@old.example', '$2y$04$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 1, 'super_admin'];
        $adminText = json_encode(['$' => 'oe-rows/1', 'table' => 'admins', 'columns' => $columns], JSON_UNESCAPED_SLASHES)."\n".json_encode($admin, JSON_UNESCAPED_SLASHES)."\n";
        $writer->addEntryFromString('db/admins.0000', 'db', $adminText, ['group' => 'core', 'rows' => 1, 'attrs' => ['table' => 'admins']]);
        // The real Spatie role that makes the administrator a super administrator (the legacy `role` column is not trusted).
        $roleText = json_encode(['$' => 'oe-rows/1', 'table' => 'roles', 'columns' => ['id', 'name', 'guard_name']], JSON_UNESCAPED_SLASHES)."
".json_encode([1, 'super_admin', 'admin'], JSON_UNESCAPED_SLASHES)."
";
        $writer->addEntryFromString('db/roles.0000', 'db', $roleText, ['group' => 'core', 'rows' => 1, 'attrs' => ['table' => 'roles']]);
        $assignText = json_encode(['$' => 'oe-rows/1', 'table' => 'model_has_roles', 'columns' => ['role_id', 'model_type', 'model_id']], JSON_UNESCAPED_SLASHES)."
".json_encode([1, \App\Models\Admin::class, 1], JSON_UNESCAPED_SLASHES)."
";
        $writer->addEntryFromString('db/model_has_roles.0000', 'db', $assignText, ['group' => 'core', 'rows' => 1, 'attrs' => ['table' => 'model_has_roles']]);
        $writer->addEntryFromString('db/settings.0000', 'db', "{\"\$\":\"oe-rows/1\",\"table\":\"settings\"}\n[1,\"a\"]\n", ['group' => 'core', 'rows' => 1, 'attrs' => ['table' => 'settings']]);

        if (in_array('catalog', $groups, true)) {
            $writer->addEntryFromString('db/products.0000', 'db', "{\"\$\":\"oe-rows/1\",\"table\":\"products\"}\n[1,\"p\"]\n", ['group' => 'catalog', 'rows' => 1, 'attrs' => ['table' => 'products']]);
        }

        $writer->seal();

        $path = $this->inboxDir().DIRECTORY_SEPARATOR.$name;
        touch($path, time() - 600);

        return $path;
    }

    /** Open the package in the inbox as the installer's package step does. */
    private function openRunnablePackage(string $name = 'shop.oeparts'): void
    {
        $this->ownerAtPackageStep();
        $this->openPackage($name)->assertRedirect(route('installer.import.package'));
    }

    /**
     * Save step 4's choices and press "Check the package". Returns the run the browser is handed over to.
     *
     * @param  array<string, mixed>  $choices  the form of step 4
     * @return array{id: string, token: string, location: string}
     */
    private function startRunFromInstaller(array $choices): array
    {
        $this->post('/install/import/choose', $choices + ['launch' => 'live', 'site_url' => 'http://shop.example'])->assertRedirect(route('installer.import.check'));

        $response = $this->post('/install/import/check');
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertSame(1, preg_match('~/migrate/run/([a-z0-9]{12,40})#t=([0-9a-f]{64})$~', $location, $matches), "the browser is handed to the runner with the token in the fragment: {$location}");

        return ['id' => $matches[1], 'token' => $matches[2], 'location' => $location];
    }

    /** @return array<string, string> */
    private function tokenHeader(string $token): array
    {
        return ['X-Migrate-Token' => $token];
    }

    /**
     * Let the runner work until the run stops running (it waits for "go", or it ended), the way the page does: one advance call
     * at a time, with the run's token.
     *
     * @return array<string, mixed> the last answer
     */
    private function advanceToConfirmation(string $id, string $token): array
    {
        $answer = [];

        for ($i = 0; $i < 40; $i++) {
            $answer = $this->postJson("/migrate/run/{$id}/advance", [], $this->tokenHeader($token))->assertOk()->json();

            if (($answer['status'] ?? '') !== 'running') {
                return $answer;
            }
        }

        $this->fail('the run never stopped running: '.json_encode($answer));
    }

    /** The acknowledgement keys the panel offers, all ticked. @return list<string> */
    private function allAcks(array $answer): array
    {
        return array_values(array_map(static fn (array $ack): string => $ack['key'], (array) ($answer['confirm']['acks'] ?? [])));
    }

    /** Why a run that should be ready is not, in one line. */
    private function whyNotReady(array $answer): string
    {
        $problems = array_filter((array) ($answer['confirm']['checks'] ?? []), static fn (array $c): bool => ($c['status'] ?? 'pass') !== 'pass');

        return json_encode(['status' => $answer['status'] ?? null, 'error' => $answer['error'] ?? null, 'checks' => array_map(static fn (array $c): string => $c['key'].':'.$c['status'].':'.$c['message'], $problems)]) ?: '';
    }
}
