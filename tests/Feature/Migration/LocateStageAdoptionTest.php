<?php

namespace Tests\Feature\Migration;

use App\Security\PassphraseThrottle;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportRunMutex;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\Stages\LocateStage;
use App\Services\Migration\Import\Stages\VerifyStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Ux\PackageUnlocker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * The installer opens a package in its own step and must not ask for the passphrase again when the run starts. It leaves the
 * unlocked keys in the escrow under an id of its own ("wiz-..."), and the locate stage may take them over. These tests are about
 * the limits of that: keys that do not belong to the file, entries that are gone, ids that are not the installer's, and anything a
 * web request might try to send.
 *
 * Every folder (inbox, run state, escrow, wrong-passphrase counters) is temporary: nothing here can touch the developer's storage.
 */
class LocateStageAdoptionTest extends TestCase
{
    use BuildsPackages;

    private const WIZARD_ID = 'wiz-0123456789abcdef01234567';

    private string $root = '';

    private int $now = 1_800_000_000;

    private KeyEscrow $escrow;

    private ImportStateStore $store;

    private PackageUnlocker $unlocker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeWorkDir();
        $this->root = $this->workDir.DIRECTORY_SEPARATOR.'state';
        mkdir($this->root, 0700, true);

        $kdf = $this->weakKdf();
        $this->app->instance(Kdf::class, $kdf);
        $this->app->instance(ImportInbox::class, new ImportInbox($this->workDir));

        $this->escrow = new KeyEscrow($this->root.DIRECTORY_SEPARATOR.'escrow', (string) config('app.key'), 86400, fn (): int => $this->now);
        $this->store = new ImportStateStore($this->root.DIRECTORY_SEPARATOR.'runs');
        $this->unlocker = new PackageUnlocker(new PassphraseThrottle($this->root.DIRECTORY_SEPARATOR.'throttle', 5, 60, 900, 86400, fn (): int => $this->now), $kdf);

        $this->app->instance(KeyEscrow::class, $this->escrow);
        $this->app->instance(ImportStateStore::class, $this->store);
        $this->app->instance(PackageUnlocker::class, $this->unlocker);

        $this->useLogFile();

        foreach ([ImportManager::class, ImportRunMutex::class, ImportStageRegistry::class, LocateStage::class, VerifyStage::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        // Only the first two stages: this file is about taking keys over, nothing that follows.
        $this->app->instance(ImportStageRegistry::class, new ImportStageRegistry([app(LocateStage::class), app(VerifyStage::class)]));
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();

        parent::tearDown();
    }

    private function manager(): ImportManager
    {
        return app(ImportManager::class);
    }

    /** The migration log goes to a file of this test, so what the engine tells the operator can be read back (and nothing lands in the developer's log). */
    private function useLogFile(): void
    {
        config(['logging.channels.migration' => ['driver' => 'single', 'path' => $this->logFile(), 'level' => 'debug', 'replace_placeholders' => true]]);
        Log::forgetChannel('migration');
    }

    private function logFile(): string
    {
        return $this->root.DIRECTORY_SEPARATOR.'migration.log';
    }

    private function logText(): string
    {
        return is_file($this->logFile()) ? (string) file_get_contents($this->logFile()) : '';
    }

    private function buildPackage(string $name, string $domain = 'old.example'): string
    {
        $writer = $this->newWriter($name, ['source' => ['domain' => $domain], 'export' => ['groups' => ['core']]]);
        $writer->addEntryFromString('report/small.json', 'report', '{"ok":true}', ['group' => 'settings']);
        $writer->seal();

        return $this->packagePath($name);
    }

    /** Open the package the way the installer's package step does and leave its keys under an id. @return array{package_id: string, fingerprint: string} */
    private function installerUnlock(string $path, string $id = self::WIZARD_ID): array
    {
        $unlocked = $this->unlocker->unlock($path, $this->passphrase, 'installer:test');
        $this->escrow->put($id, $unlocked['package_id'], $unlocked['keys']);

        return ['package_id' => $unlocked['package_id'], 'fingerprint' => $unlocked['fingerprint']];
    }

    /** @return array{state: ImportState, token: string, progress: array<string, mixed>} */
    private function startRun(string $name, ?string $passphrase = null, array $options = ['door' => 'installer', 'adopt_escrow' => self::WIZARD_ID])
    {
        return $this->manager()->start(['inbox' => $name], $passphrase, ['door' => 'installer'], 'import', 'installer:test', $options);
    }

    // --- the hand-over ---

    #[Test]
    public function the_run_takes_over_the_keys_and_the_installers_entry_is_removed(): void
    {
        $path = $this->buildPackage('shop.oeparts');
        $unlocked = $this->installerUnlock($path);

        $result = $this->startRun('shop.oeparts');
        $state = $result['state'];

        $this->assertTrue($state->flag('locate.done'));
        $this->assertFalse($state->flag('needs_passphrase'));
        $this->assertSame($unlocked['fingerprint'], $state->package['fingerprint'], 'the fingerprint comes from the file');
        $this->assertSame($unlocked['package_id'], $state->package['package_id']);
        $this->assertNotNull($this->escrow->get(ImportContext::escrowId($state->id)), 'the run has its own copy');
        $this->assertNull($this->escrow->get(self::WIZARD_ID), 'the installer\'s entry was deleted');
        $this->assertArrayHasKey('identity', $state->package);
    }

    #[Test]
    public function nothing_secret_reaches_the_state_file(): void
    {
        $path = $this->buildPackage('shop.oeparts');
        $this->installerUnlock($path);

        $result = $this->startRun('shop.oeparts');

        $raw = (string) file_get_contents($this->store->file($result['state']->id)->path());
        $this->assertStringNotContainsString($this->passphrase, $raw);
        $this->assertStringNotContainsString($result['token'], $raw);
        $this->assertStringNotContainsString(bin2hex($this->escrow->get(ImportContext::escrowId($result['state']->id))->keys->data()), $raw);
    }

    // --- what is refused ---

    #[Test]
    public function keys_of_another_package_are_never_adopted(): void
    {
        $this->buildPackage('first.oeparts', 'first.example');
        $second = $this->buildPackage('second.oeparts', 'second.example');
        $this->installerUnlock($this->packagePath('first.oeparts'));

        $result = $this->startRun('second.oeparts');
        $state = $result['state'];

        $this->assertTrue($state->flag('needs_passphrase'), 'the passphrase is asked for instead');
        $this->assertFalse($state->flag('locate.done'));
        $this->assertNull($this->escrow->get(ImportContext::escrowId($state->id)));
        $this->assertNotNull($this->escrow->get(self::WIZARD_ID), 'the entry was not consumed');
        $this->assertFileExists($second);
    }

    #[Test]
    public function an_entry_that_expired_asks_for_the_passphrase(): void
    {
        $path = $this->buildPackage('shop.oeparts');
        $this->installerUnlock($path);

        $this->now += 86401;
        $state = $this->startRun('shop.oeparts')['state'];

        $this->assertTrue($state->flag('needs_passphrase'));
        $this->assertFalse($state->flag('locate.done'));
    }

    #[Test]
    public function a_missing_entry_asks_for_the_passphrase_without_an_error(): void
    {
        $this->buildPackage('shop.oeparts');

        $result = $this->startRun('shop.oeparts');

        $this->assertTrue($result['state']->flag('needs_passphrase'));
        $this->assertNull($result['progress']['error']);
    }

    #[Test]
    public function an_id_that_is_not_the_installers_is_ignored(): void
    {
        $path = $this->buildPackage('shop.oeparts');

        foreach (['import-0123456789abcdef', 'wiz-short', 'wiz-0123456789ABCDEF01234567', 'wiz-0123456789abcdef012345678', '../wiz-0123456789abcdef01234567', 'other-0123456789abcdef01234567'] as $id) {
            $unlocked = $this->unlocker->unlock($path, $this->passphrase, 'installer:test');

            try {
                $this->escrow->put($id, $unlocked['package_id'], $unlocked['keys']);
            } catch (\InvalidArgumentException) {
                // An id the escrow itself refuses cannot be adopted either.
            }

            $state = $this->startRun('shop.oeparts', null, ['door' => 'installer', 'adopt_escrow' => $id])['state'];

            $this->assertTrue($state->flag('needs_passphrase'), "ignored: {$id}");
            $this->assertFalse($state->flag('locate.done'), "ignored: {$id}");
        }
    }

    #[Test]
    public function a_non_string_option_is_ignored(): void
    {
        $this->buildPackage('shop.oeparts');
        $this->installerUnlock($this->packagePath('shop.oeparts'));

        foreach ([['wiz-x'], 12, true, null] as $value) {
            $state = $this->startRun('shop.oeparts', null, ['door' => 'installer', 'adopt_escrow' => $value])['state'];

            $this->assertTrue($state->flag('needs_passphrase'));
        }
    }

    #[Test]
    public function without_the_option_nothing_is_adopted_even_if_an_entry_exists(): void
    {
        $this->buildPackage('shop.oeparts');
        $this->installerUnlock($this->packagePath('shop.oeparts'));

        $state = $this->startRun('shop.oeparts', null, ['door' => 'cli'])['state'];

        $this->assertTrue($state->flag('needs_passphrase'));
        $this->assertNotNull($this->escrow->get(self::WIZARD_ID));
    }

    #[Test]
    public function a_typed_passphrase_still_works_and_leaves_the_installers_entry_alone(): void
    {
        $this->buildPackage('shop.oeparts');
        $this->installerUnlock($this->packagePath('shop.oeparts'));

        $state = $this->startRun('shop.oeparts', $this->passphrase)['state'];

        $this->assertTrue($state->flag('locate.done'));
        $this->assertNotNull($this->escrow->get(self::WIZARD_ID), 'only an adoption consumes the entry');
    }

    #[Test]
    public function a_run_that_lost_its_key_later_asks_for_the_passphrase_rather_than_adopting_again(): void
    {
        $this->buildPackage('shop.oeparts');
        $this->installerUnlock($this->packagePath('shop.oeparts'));
        $state = $this->startRun('shop.oeparts')['state'];
        $this->assertTrue($state->flag('locate.done'));

        // The installer's entry is gone (it was consumed); the run's own key expires.
        $this->escrow->delete(ImportContext::escrowId($state->id));
        $progress = $this->manager()->advance($state->id, 0.0);

        $this->assertTrue($progress['needs_passphrase']);
    }

    // --- what a web request can and cannot do ---

    #[Test]
    public function the_runner_cannot_be_made_to_adopt_anything(): void
    {
        $path = $this->buildPackage('shop.oeparts');
        $this->installerUnlock($path);

        // A run started by the command line (no adopt option), then a request that tries to add one.
        $result = $this->startRun('shop.oeparts', null, ['door' => 'cli']);
        $id = $result['state']->id;

        foreach (['POST' => 'advance', 'GET' => 'status'] as $method => $action) {
            $response = $this->call($method, "/migrate/run/{$id}/{$action}", ['adopt_escrow' => self::WIZARD_ID, 'options' => ['adopt_escrow' => self::WIZARD_ID]], [], [], [
                'HTTP_X_MIGRATE_TOKEN' => $result['token'],
                'HTTP_ACCEPT' => 'application/json',
            ], (string) json_encode(['adopt_escrow' => self::WIZARD_ID, 'options' => ['adopt_escrow' => self::WIZARD_ID]]));
            $response->assertOk();
        }

        $state = $this->store->find($id);
        $this->assertArrayNotHasKey('adopt_escrow', $state->options);
        $this->assertFalse($state->flag('locate.done'));
        $this->assertNotNull($this->escrow->get(self::WIZARD_ID), 'the installer\'s entry is untouched');
    }

    #[Test]
    public function no_route_of_the_runner_takes_an_option_from_a_request(): void
    {
        $this->buildPackage('shop.oeparts');
        $this->installerUnlock($this->packagePath('shop.oeparts'));
        $result = $this->startRun('shop.oeparts', null, ['door' => 'cli']);
        $id = $result['state']->id;
        $before = $this->store->find($id)->options;

        $routes = array_values(array_filter(Route::getRoutes()->getRoutes(), static fn ($route): bool => str_starts_with((string) $route->getName(), 'migrate.run.')));
        $this->assertGreaterThanOrEqual(3, count($routes), 'the runner has its routes (page, status, advance, and the confirm call once it exists)');

        $wizard = self::WIZARD_ID;
        $payload = [
            'adopt_escrow' => $wizard,
            'options' => ['adopt_escrow' => $wizard, 'door' => 'installer'],
            'passphrase' => $this->passphrase,
            'acks' => ['target_populated'],
            'typed' => 'REPLACE',
            'replace_populated' => true,
        ];
        $server = ['HTTP_X_MIGRATE_TOKEN' => $result['token'], 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_ADOPT_ESCROW' => $wizard];

        foreach ($routes as $route) {
            $uri = '/'.str_replace('{id}', $id, $route->uri());

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                // As a query string, as a form body and as a JSON body.
                $this->call($method, $uri.'?'.http_build_query($payload), [], [], [], $server);
                $this->call($method, $uri, $payload, [], [], $server);
                $this->call($method, $uri, [], [], [], $server + ['CONTENT_TYPE' => 'application/json'], (string) json_encode($payload));
            }
        }

        $state = $this->store->find($id);
        $this->assertSame($before, $state->options, 'the options of the run are what the code that started it gave');
        $this->assertArrayNotHasKey('adopt_escrow', $state->options);
        $this->assertFalse($state->flag('locate.done'), 'nothing was adopted');
        $this->assertFalse($state->flag('confirmed'));
        $this->assertNotNull($this->escrow->get($wizard), 'the installer\'s entry is untouched');
        $this->assertStringNotContainsString($wizard, (string) file_get_contents($this->store->file($id)->path()));
    }

    #[Test]
    public function only_the_installers_own_code_names_the_option(): void
    {
        $allowed = static fn (string $relative): bool => $relative === 'app/Services/Migration/Import/Stages/LocateStage.php'
            || str_starts_with($relative, 'app/Services/Migration/Installer/')
            || str_starts_with($relative, 'app/Http/Controllers/Installer');

        $offenders = [];
        $base = str_replace('\\', '/', base_path()).'/';

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($base));

            if (str_contains((string) file_get_contents($file->getPathname()), 'adopt_escrow') && ! $allowed($relative)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'a request-facing class that names adopt_escrow could be made to set it');
    }

    // --- the operator is told why (the person is not) ---

    /** One way to refuse the hand-over per reason code. @return array<string, array{0: string, 1: array<string, bool>, 2: array<string, mixed>}> */
    public static function refusals(): array
    {
        $installer = ['door' => 'installer', 'adopt_escrow' => self::WIZARD_ID];

        return [
            'no option' => ['no_option', [], ['door' => 'cli']],
            'an id that is not the installers' => ['bad_id', [], ['door' => 'installer', 'adopt_escrow' => '../../etc/passwd-0123456789abcdef']],
            'an option of the wrong type' => ['bad_id', [], ['door' => 'installer', 'adopt_escrow' => ['wiz-x']]],
            'no entry' => ['no_entry', [], $installer],
            'keys of another package' => ['keys_do_not_open_file', ['otherPackage' => true], $installer],
            'the right keys filed under another package id' => ['package_id_mismatch', ['wrongPackageId' => true], $installer],
            'an entry that cannot be read' => ['exception', ['damagedEntry' => true], $installer],
        ];
    }

    /**
     * @param  array<string, bool>  $setup
     * @param  array<string, mixed>  $options
     */
    #[Test]
    #[DataProvider('refusals')]
    public function every_refusal_is_logged_as_a_reason_code_and_nothing_secret_is_in_it(string $reason, array $setup, array $options): void
    {
        $path = $this->buildPackage('shop.oeparts');

        if ($setup['otherPackage'] ?? false) {
            $this->buildPackage('other.oeparts', 'other.example');
            $this->installerUnlock($this->packagePath('other.oeparts'));
        }

        if ($setup['wrongPackageId'] ?? false) {
            $this->escrow->put(self::WIZARD_ID, str_repeat('f', 32), $this->unlocker->unlock($path, $this->passphrase, 'installer:test')['keys']);
        }

        if ($setup['damagedEntry'] ?? false) {
            $this->installerUnlock($path);
            file_put_contents($this->root.DIRECTORY_SEPARATOR.'escrow'.DIRECTORY_SEPARATOR.self::WIZARD_ID.'.key', 'not an escrow entry');
        }

        $result = $this->startRun('shop.oeparts', null, $options);

        // The person's side is the same quiet question in every case.
        $this->assertTrue($result['state']->flag('needs_passphrase'));
        $this->assertFalse($result['state']->flag('locate.done'));
        $this->assertSame('Type the passphrase of the package.', $result['progress']['detail']);
        $this->assertNull($result['progress']['error']);
        $this->assertArrayNotHasKey('input_error', $result['progress']);
        $shown = (string) json_encode($result['progress']);
        $this->assertStringNotContainsString($reason, $shown);
        $this->assertStringNotContainsString('wiz-', $shown);

        // The operator's side: one warning with the code.
        $log = $this->logText();
        $this->assertSame(1, substr_count($log, 'were not used'), 'exactly one line');
        $this->assertStringContainsString('WARNING', $log);
        $this->assertStringContainsString('"reason":"'.$reason.'"', $log);
        $this->assertStringContainsString($result['state']->id, $log, 'the run is named');

        if ($reason === 'exception') {
            $this->assertStringContainsString('"exception":"App\\\\Services\\\\Migration\\\\MigrationException"', $log, 'the class of the exception');
            $this->assertStringContainsString('"code":"key_escrow_lost"', $log);
        }

        // Nothing that could open the package or locate it.
        $this->assertStringNotContainsString($this->passphrase, $log);
        $this->assertStringNotContainsString(self::WIZARD_ID, $log, 'the escrow id');
        $this->assertStringNotContainsString(substr(self::WIZARD_ID, 4), $log, 'not even the part after the prefix');
        $this->assertStringNotContainsString($this->workDir, $log, 'no path');
        $this->assertStringNotContainsString('shop.oeparts', $log, 'no file name');
        $this->assertStringNotContainsString($result['token'], $log, 'the runner token');
        $this->assertStringNotContainsString('not an escrow entry', $log, 'no content read from a file');

        foreach ($this->unlocker->unlock($path, $this->passphrase, 'installer:test')['keys']->subkeys() as $key) {
            $this->assertStringNotContainsString(bin2hex($key), $log);
            $this->assertStringNotContainsString(base64_encode($key), $log);
        }
    }

    #[Test]
    public function a_refusal_is_logged_once_per_run_not_on_every_poll(): void
    {
        $this->buildPackage('shop.oeparts');
        $result = $this->startRun('shop.oeparts');

        foreach (range(1, 4) as $_) {
            $progress = $this->manager()->advance($result['state']->id, 0.0);
            $this->assertTrue($progress['needs_passphrase']);
        }

        $this->assertSame(1, substr_count($this->logText(), 'were not used'));
        $this->assertTrue($this->store->find($result['state']->id)->flag('adopt.refused'));
    }

    #[Test]
    public function after_a_refusal_the_typed_passphrase_still_opens_the_run(): void
    {
        $this->buildPackage('shop.oeparts');
        $result = $this->startRun('shop.oeparts');
        $this->assertTrue($result['state']->flag('needs_passphrase'));

        $this->manager()->provideSecret($result['state']->id, $this->passphrase, 'installer:test');

        $state = $this->store->find($result['state']->id);
        $this->assertTrue($state->flag('locate.done'));
        $this->assertFalse($state->flag('needs_passphrase'));
        $this->assertSame(1, substr_count($this->logText(), 'were not used'), 'typing the passphrase logs nothing more');
    }

    #[Test]
    public function a_successful_hand_over_writes_no_warning(): void
    {
        $this->buildPackage('shop.oeparts');
        $this->installerUnlock($this->packagePath('shop.oeparts'));

        $state = $this->startRun('shop.oeparts')['state'];

        $this->assertTrue($state->flag('locate.done'));
        $this->assertFalse($state->flag('adopt.refused'));
        $this->assertStringNotContainsString('were not used', $this->logText());
    }
}
