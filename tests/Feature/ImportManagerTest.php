<?php

namespace Tests\Feature;

use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportRunMutex;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\Stages\LocateStage;
use App\Services\Migration\Import\Stages\VerifyStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationPaths;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Ux\PackageUnlocker;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * Phase 6.3: the import manager and its first two stages, locate and verify. Everything here must hold WITHOUT a
 * single database query: the engine reads the package and writes its own state file, nothing else.
 */
class ImportManagerTest extends TestCase
{
    use BuildsPackages;

    /** @var array<int, string> */
    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeWorkDir();
        $this->app->instance(Kdf::class, $this->weakKdf());
        foreach ([ImportManager::class, ImportStageRegistry::class, PackageUnlocker::class, LocateStage::class, VerifyStage::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        // This file is about locating and verifying, which must run with no database at all: give the manager only those two stages.
        $this->app->instance(ImportStageRegistry::class, new ImportStageRegistry([
            app(LocateStage::class),
            app(VerifyStage::class),
        ]));

        $this->clearThrottle();
        $this->before = array_map(fn (ImportState $s): string => $s->id, (new ImportStateStore)->all());
        config(['migration.import.verify_budget_bytes' => 64 * 1024 * 1024]);
    }

    protected function tearDown(): void
    {
        foreach ((new ImportStateStore)->all() as $state) {
            if (! in_array($state->id, $this->before, true)) {
                app(KeyEscrow::class)->delete('import-'.$state->id);
                exec('rm -rf '.escapeshellarg((new ImportStateStore)->directory($state->id)));
            }
        }

        $this->clearThrottle();
        $this->removeWorkDir();

        parent::tearDown();
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

    /** @param  array<int, string>|null  $groups */
    private function makePackage(string $name = 'site.oeparts', ?array $groups = null, array $extraDb = [], bool $withIndex = true): string
    {
        $groups ??= ['core', 'catalog', 'media'];
        $writer = $this->newWriter($name, ['source' => ['domain' => 'old.example'], 'export' => ['groups' => $groups]]);

        $writer->addEntryFromString('db/settings.0000', 'db', "{\"\$\":\"oe-rows/1\",\"table\":\"settings\"}\n[1,\"a\"]\n", ['group' => 'core', 'rows' => 1, 'attrs' => ['table' => 'settings']]);
        $writer->addEntryFromString('db/products.0000', 'db', "{\"\$\":\"oe-rows/1\",\"table\":\"products\"}\n[1,\"p\"]\n", ['group' => 'catalog', 'rows' => 1, 'attrs' => ['table' => 'products']]);

        foreach ($extraDb as $entryName => $table) {
            $writer->addEntryFromString($entryName, 'db', "{\"table\":\"{$table}\"}\n", ['group' => 'core', 'rows' => 0, 'attrs' => ['table' => $table]]);
        }

        if ($withIndex) {
            $lines = implode("\n", [
                '{"d":"public","p":"media/a.jpg","s":1,"m":1}',
                '{"d":"public","p":"media/.env","s":1,"m":1}',
                '{"d":"public","p":"media/shell.php","s":1,"m":1}',
                '{"d":"public","p":"../../etc/passwd","s":1,"m":1}',
                '{"d":"nodisk","p":"x","s":1,"m":1}',
                '{"d":"public","p":"elsewhere/outside.png","s":1,"m":1}',
                'this is not json',
            ])."\n";
            $writer->addEntryFromString('files/index.000', 'files-index', $lines, ['group' => 'media', 'rows' => 7, 'codec' => 'none']);
        }

        $writer->seal();

        return $this->packagePath($name);
    }

    // ---- starting ---------------------------------------------------------------------------------------------------

    #[Test]
    public function starting_opens_the_package_stores_only_the_keys_in_the_escrow_and_hands_back_the_token_once(): void
    {
        $path = $this->makePackage();
        $queries = $this->countQueries();

        $result = $this->manager()->start(['path' => $path], $this->passphrase, ['email' => 'a@b.test']);

        $state = $result['state'];
        $this->assertSame('verify', $state->stage, 'locate is done, verify is next');
        $this->assertTrue($state->flag('locate.done'));
        $this->assertFalse($state->flag('needs_passphrase'));
        $this->assertNotNull(app(KeyEscrow::class)->get('import-'.$state->id));
        $this->assertSame(64, strlen($result['token']));
        $this->assertSame(hash('sha256', $result['token']), $state->tokenHash);

        $raw = (string) file_get_contents($this->store()->file($state->id)->path());
        $this->assertStringNotContainsString($result['token'], $raw);
        $this->assertStringNotContainsString($this->passphrase, $raw);
        $this->assertSame(32, strlen($state->package['package_id']));
        $this->assertNotEmpty($state->package['fingerprint']);
        $this->assertSame(realpath($path), $state->package['path']);
        $this->assertSame([], $queries(), 'locating a package runs no query at all');
    }

    #[Test]
    public function a_missing_package_is_refused_before_any_run_is_created(): void
    {
        $count = count($this->store()->all());

        try {
            $this->manager()->start(['path' => $this->packagePath('nope.oeparts')], $this->passphrase);
            $this->fail('expected a refusal');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::PackageNotFound, $e->errorCode);
        }

        $this->assertCount($count, $this->store()->all());
    }

    #[Test]
    public function a_file_that_is_not_a_package_ends_the_run_as_failed_with_a_plain_code_and_leaves_no_key(): void
    {
        file_put_contents($this->packagePath('junk.oeparts'), random_bytes(400));

        $result = $this->manager()->start(['path' => $this->packagePath('junk.oeparts')], $this->passphrase);

        $this->assertSame('failed', $result['progress']['status']);
        $this->assertSame(MigrationErrorCode::PackageNotOeparts->value, $result['progress']['error']['code']);
        $this->assertNull(app(KeyEscrow::class)->get('import-'.$result['state']->id));
        $this->assertFalse($result['state']->isDestructive());
    }

    // ---- the passphrase --------------------------------------------------------------------------------------------

    #[Test]
    public function a_wrong_passphrase_leaves_the_run_waiting_so_the_person_can_try_again(): void
    {
        $path = $this->makePackage();

        $result = $this->manager()->start(['path' => $path], 'Wrong-Passphrase-1234');

        $this->assertSame('running', $result['progress']['status']);
        $this->assertSame('locate', $result['progress']['stage']);
        $this->assertTrue($result['progress']['needs_passphrase']);
        $this->assertSame('passphrase_wrong', $result['progress']['input_error']);
        $this->assertNull(app(KeyEscrow::class)->get('import-'.$result['state']->id), 'no key for a wrong passphrase');

        $retry = $this->manager()->provideSecret($result['state']->id, $this->passphrase);

        $this->assertSame('verify', $retry['stage']);
        $this->assertFalse($retry['needs_passphrase']);
        $this->assertNotNull(app(KeyEscrow::class)->get('import-'.$result['state']->id));
    }

    #[Test]
    public function after_a_few_wrong_tries_the_door_is_locked_even_for_the_right_passphrase(): void
    {
        $path = $this->makePackage();
        $result = $this->manager()->start(['path' => $path], 'wrong-1');

        for ($i = 2; $i <= 5; $i++) {
            $this->manager()->provideSecret($result['state']->id, "wrong-{$i}");
        }

        $locked = $this->manager()->provideSecret($result['state']->id, $this->passphrase);

        $this->assertSame('passphrase_locked', $locked['input_error']);
        $this->assertGreaterThan(0, $locked['input_params']['seconds']);
        $this->assertNull(app(KeyEscrow::class)->get('import-'.$result['state']->id));
    }

    #[Test]
    public function an_empty_passphrase_is_asked_for_again_not_treated_as_a_failure(): void
    {
        $path = $this->makePackage();

        $result = $this->manager()->start(['path' => $path], '   ');

        $this->assertSame('passphrase_empty', $result['progress']['input_error']);
        $this->assertSame('running', $result['progress']['status']);
    }

    #[Test]
    public function with_no_passphrase_at_all_the_run_waits_for_one(): void
    {
        $path = $this->makePackage();

        $result = $this->manager()->start(['path' => $path], null);

        $this->assertTrue($result['progress']['needs_passphrase']);
        $this->assertSame('locate', $result['progress']['stage']);
    }

    #[Test]
    public function when_the_stored_key_has_expired_a_person_types_the_passphrase_again_and_the_run_goes_on(): void
    {
        $path = $this->makePackage();
        $result = $this->manager()->start(['path' => $path], $this->passphrase);
        $id = $result['state']->id;
        app(KeyEscrow::class)->delete('import-'.$id);

        $stuck = $this->manager()->advance($id, 30.0);
        $this->assertTrue($stuck['needs_passphrase']);
        $this->assertSame('verify', $stuck['stage'], 'it did not start over');

        $this->manager()->provideSecret($id, $this->passphrase);
        $done = $this->manager()->advance($id, 30.0);

        $this->assertSame('awaiting_confirmation', $done['status']);
        $this->assertFalse($done['needs_passphrase']);
    }

    // ---- verify -------------------------------------------------------------------------------------------------------

    #[Test]
    public function a_good_package_is_verified_end_to_end_with_no_database_query_and_the_facts_are_recorded(): void
    {
        $path = $this->makePackage();
        $result = $this->manager()->start(['path' => $path], $this->passphrase);
        $queries = $this->countQueries();

        $progress = $this->manager()->advance($result['state']->id, 30.0);

        $this->assertSame('awaiting_confirmation', $progress['status']);
        $this->assertSame(100.0, $progress['percent'], 'a run waiting for confirmation has finished every stage it can');
        $state = $this->store()->find($result['state']->id);
        $this->assertTrue($state->package['verified']);
        $this->assertSame(['settings' => 1, 'products' => 1], $state->package['tables']);
        $this->assertSame(['core', 'catalog', 'media'], $state->package['groups']);
        $this->assertSame(7, $state->package['files_in_index']);
        $this->assertSame(6, array_sum($state->package['rejected_paths']), 'the .env, the .php, the traversal, the unknown disk, the file outside every root and the bad line');
        $this->assertSame([], $queries(), 'verifying runs no query at all');
    }

    #[Test]
    public function the_rejected_paths_are_counted_by_reason_never_fatal(): void
    {
        $path = $this->makePackage();
        $result = $this->manager()->start(['path' => $path], $this->passphrase);

        $this->manager()->advance($result['state']->id, 30.0);

        $rejected = $this->store()->find($result['state']->id)->package['rejected_paths'];
        $this->assertSame(1, $rejected['unknown_disk']);
        $this->assertSame(1, $rejected['bad_row']);
        $this->assertGreaterThanOrEqual(3, count($rejected), 'several different reasons');
    }

    #[Test]
    public function a_damaged_entry_fails_the_run_before_anything_changes_and_drops_the_key(): void
    {
        $path = $this->makePackage();
        $result = $this->manager()->start(['path' => $path], $this->passphrase);
        $id = $result['state']->id;

        // Flip one byte in the middle of the file, then put the identity-visible parts back so ONLY the integrity pass can notice.
        $size = filesize($path);
        $mtime = filemtime($path);
        $handle = fopen($path, 'r+b');
        fseek($handle, (int) ($size / 2));
        $byte = fread($handle, 1);
        fseek($handle, (int) ($size / 2));
        fwrite($handle, chr(ord($byte) ^ 0xFF));
        fclose($handle);
        touch($path, $mtime);
        $queries = $this->countQueries();

        $progress = $this->manager()->advance($id, 30.0);

        $this->assertSame('failed', $progress['status']);
        $this->assertContains($progress['error']['code'], [MigrationErrorCode::PackageCorrupt->value, MigrationErrorCode::PackageHashMismatch->value]);
        $this->assertFalse($this->store()->find($id)->isDestructive());
        $this->assertNull(app(KeyEscrow::class)->get('import-'.$id));
        $this->assertSame([], $queries());
    }

    #[Test]
    public function touching_the_package_between_locate_and_verify_stops_the_run_harmlessly(): void
    {
        $path = $this->makePackage();
        $result = $this->manager()->start(['path' => $path], $this->passphrase);
        touch($path, time() + 500);

        $progress = $this->manager()->advance($result['state']->id, 30.0);

        $this->assertSame('failed', $progress['status']);
        $this->assertSame(MigrationErrorCode::PackageChanged->value, $progress['error']['code']);
    }

    #[Test]
    public function a_package_with_a_table_this_application_does_not_have_is_refused(): void
    {
        $path = $this->makePackage('hostile.oeparts', null, ['db/evil.0000' => 'admins_backdoor']);
        $result = $this->manager()->start(['path' => $path], $this->passphrase);

        $progress = $this->manager()->advance($result['state']->id, 30.0);

        $this->assertSame('failed', $progress['status']);
        $this->assertSame(MigrationErrorCode::PackageCorrupt->value, $progress['error']['code']);
        $this->assertStringContainsString('admins_backdoor', (string) json_encode($progress['error']['params']));
    }

    #[Test]
    public function a_table_from_a_group_the_package_does_not_carry_is_refused(): void
    {
        // orders belongs to the sales group, which this package does not list.
        $path = $this->makePackage('sneaky.oeparts', ['core', 'catalog'], ['db/orders.0000' => 'orders'], false);
        $result = $this->manager()->start(['path' => $path], $this->passphrase);

        $progress = $this->manager()->advance($result['state']->id, 30.0);

        $this->assertSame('failed', $progress['status']);
        $this->assertSame(MigrationErrorCode::PackageCorrupt->value, $progress['error']['code']);
    }

    #[Test]
    public function a_package_that_claims_the_system_group_is_refused(): void
    {
        $path = $this->makePackage('system.oeparts', ['core', 'system'], [], false);
        $result = $this->manager()->start(['path' => $path], $this->passphrase);

        $progress = $this->manager()->advance($result['state']->id, 30.0);

        $this->assertSame('failed', $progress['status']);
        $this->assertSame(MigrationErrorCode::PackageCorrupt->value, $progress['error']['code']);
    }

    // ---- resuming -------------------------------------------------------------------------------------------------

    #[Test]
    public function a_run_advanced_in_many_tiny_steps_by_fresh_managers_ends_exactly_like_one_advanced_in_one_go(): void
    {
        config(['migration.import.verify_budget_bytes' => 1]);
        $path = $this->makePackage('site.oeparts', null, $this->manyCoreEntries());
        $once = $this->manager()->start(['path' => $path], $this->passphrase);
        config(['migration.import.verify_budget_bytes' => 64 * 1024 * 1024]);
        $this->manager()->advance($once['state']->id, 60.0);
        $reference = $this->store()->find($once['state']->id)->package;

        config(['migration.import.verify_budget_bytes' => 1]);
        $stepped = $this->manager()->start(['path' => $path], $this->passphrase);
        $steps = 0;

        do {
            $this->app->forgetInstance(ImportManager::class); // a new process, as far as the manager goes
            $progress = $this->manager()->advance($stepped['state']->id, 0.0);
            $this->assertLessThan(5000, ++$steps);
        } while ($progress['status'] === 'running');

        $this->assertGreaterThan(10, $steps, 'it really was done in many steps');
        $this->assertSame('awaiting_confirmation', $progress['status']);

        $got = $this->store()->find($stepped['state']->id)->package;
        foreach (['tables', 'groups', 'files_in_index', 'rejected_paths', 'verified'] as $key) {
            $this->assertSame($reference[$key], $got[$key], $key);
        }
    }

    /** @return array<string, string> entry name => core table, to give a package enough entries to be verified in many steps */
    private function manyCoreEntries(): array
    {
        $entries = [];

        foreach (['languages', 'language_strings', 'roles', 'permissions', 'role_has_permissions', 'model_has_roles', 'model_has_permissions', 'sequences', 'admins'] as $i => $table) {
            $entries[sprintf('db/%s.%04d', $table, $i)] = $table;
        }

        return $entries;
    }

    #[Test]
    public function the_progress_never_goes_backwards_and_reaches_the_end(): void
    {
        config(['migration.import.verify_budget_bytes' => 1]);
        $path = $this->makePackage('site.oeparts', null, $this->manyCoreEntries());
        $result = $this->manager()->start(['path' => $path], $this->passphrase);
        $last = -1.0;

        do {
            $progress = $this->manager()->advance($result['state']->id, 0.0);
            $this->assertGreaterThanOrEqual($last, $progress['percent']);
            $last = $progress['percent'];
        } while ($progress['status'] === 'running');

        $this->assertGreaterThan(0, $last);
    }

    #[Test]
    public function only_one_process_advances_a_run_at_a_time(): void
    {
        $path = $this->makePackage();
        $result = $this->manager()->start(['path' => $path], $this->passphrase);
        $mutex = new ImportRunMutex($this->store());
        $held = $mutex->acquire($result['state']->id);

        $busy = $this->manager()->advance($result['state']->id, 30.0);
        $mutex->release($held);

        $this->assertTrue($busy['busy']);
        $this->assertSame('verify', $busy['stage'], 'nothing moved');
        $this->assertArrayNotHasKey('busy', $this->manager()->advance($result['state']->id, 30.0));
    }

    #[Test]
    public function a_finished_or_unknown_run_answers_without_doing_anything(): void
    {
        $this->assertSame(['found' => false], $this->manager()->status('0123456789abcdef'));
        $this->assertFalse($this->manager()->advance('0123456789abcdef', 5.0)['found']);

        $path = $this->makePackage();
        $result = $this->manager()->start(['path' => $path], $this->passphrase);
        $this->manager()->advance($result['state']->id, 30.0);
        $again = $this->manager()->advance($result['state']->id, 30.0);

        $this->assertSame('awaiting_confirmation', $again['status']);
        $this->assertFalse($again['advanced']);
    }

    // ---- discarding ------------------------------------------------------------------------------------------------

    #[Test]
    public function a_run_that_changed_nothing_can_be_discarded_with_its_key(): void
    {
        $path = $this->makePackage();
        $result = $this->manager()->start(['path' => $path], $this->passphrase);
        $id = $result['state']->id;

        $this->manager()->discard($id);

        $this->assertNull($this->store()->find($id));
        $this->assertNull(app(KeyEscrow::class)->get('import-'.$id));
    }

    #[Test]
    public function a_run_that_has_changed_the_site_cannot_simply_be_discarded(): void
    {
        $path = $this->makePackage();
        $result = $this->manager()->start(['path' => $path], $this->passphrase);
        $this->store()->update($result['state']->id, fn (ImportState $s) => $s->markDestructive());

        $this->expectException(\LogicException::class);
        $this->manager()->discard($result['state']->id);
    }

    // ---- the inbox ----------------------------------------------------------------------------------------------------

    #[Test]
    public function a_web_run_may_only_use_a_correctly_named_file_that_really_is_in_the_inbox(): void
    {
        $inboxDir = $this->workDir.DIRECTORY_SEPARATOR.'inbox';
        mkdir($inboxDir, 0775, true);
        $inbox = new ImportInbox($inboxDir);
        file_put_contents($inboxDir.'/good.oeparts', 'x');
        file_put_contents($this->workDir.'/outside.oeparts', 'x');
        @symlink($this->workDir.'/outside.oeparts', $inboxDir.'/link.oeparts');

        $this->assertSame(realpath($inboxDir.'/good.oeparts'), $inbox->resolve('good.oeparts'));

        foreach (['../outside.oeparts', 'link.oeparts', 'good.txt', '.hidden.oeparts', 'sub/good.oeparts', 'good.oeparts/', '', str_repeat('a', 160).'.oeparts', 'a..b.oeparts', "go\0od.oeparts", 'missing.oeparts'] as $bad) {
            try {
                $inbox->resolve($bad);
                $this->fail("'{$bad}' must be refused");
            } catch (MigrationException $e) {
                $this->assertSame(MigrationErrorCode::PackageNotFound, $e->errorCode);
            }
        }
    }

    #[Test]
    public function the_inbox_lists_only_real_packages_newest_first(): void
    {
        $inboxDir = $this->workDir.DIRECTORY_SEPARATOR.'inbox2';
        mkdir($inboxDir, 0775, true);
        file_put_contents($inboxDir.'/old.oeparts', 'x');
        touch($inboxDir.'/old.oeparts', time() - 500);
        file_put_contents($inboxDir.'/new.oeparts', 'xy');
        file_put_contents($inboxDir.'/notes.txt', 'x');
        file_put_contents($inboxDir.'/.hidden.oeparts', 'x');

        $this->assertSame(['new.oeparts', 'old.oeparts'], array_column((new ImportInbox($inboxDir))->list(), 'name'));
    }

    #[Test]
    public function a_run_from_the_inbox_is_started_by_name_and_remembers_it_came_from_there(): void
    {
        $inboxDir = MigrationPaths::inbox();
        @mkdir($inboxDir, 0775, true);
        $this->makePackage('from-inbox.oeparts');
        $target = $inboxDir.'/oe-test-from-inbox.oeparts';
        copy($this->packagePath('from-inbox.oeparts'), $target);

        try {
            $result = $this->manager()->start(['inbox' => 'oe-test-from-inbox.oeparts'], $this->passphrase);

            $this->assertSame('inbox', $result['state']->package['origin']);
            $this->assertSame('verify', $result['state']->stage);

            @unlink($target);
            $progress = $this->manager()->advance($result['state']->id, 30.0);
            $this->assertSame('failed', $progress['status'], 'the file left the inbox: the run stops');
        } finally {
            @unlink($target);
        }
    }

    // ---- the registry ----------------------------------------------------------------------------------------------

    #[Test]
    public function the_registry_knows_its_order_and_the_dry_run_stops_at_preflight(): void
    {
        $registry = ImportStageRegistry::forApplication();

        $this->assertSame(['locate', 'verify', 'ensure_schema', 'preflight', 'recovery_point', 'arm', 'db_clear', 'db_index_drop', 'db_data', 'db_index_restore', 'db_audit', 'db_seed_defaults', 'files'], $registry->keys('import'));
        $this->assertSame('verify', $registry->nextAfter('locate'));
        $this->assertSame('ensure_schema', $registry->nextAfter('verify'));
        $this->assertSame('recovery_point', $registry->nextAfter('preflight'));
        $this->assertSame('arm', $registry->nextAfter('recovery_point'));
        $this->assertNull($registry->nextAfter('files'));
        $this->assertSame(0.0, $registry->percent('locate', 0.0));
        $this->assertSame(7.7, $registry->percent('verify', 0.0));
        $this->assertSame(100.0, $registry->percent('files', 1.0));
        $this->assertSame(0.0, $registry->percent('no_such_stage', 0.5));
    }

    #[Test]
    public function a_stage_registered_twice_is_a_programming_error(): void
    {
        $locate = app(LocateStage::class);

        $this->expectException(\LogicException::class);
        new ImportStageRegistry([$locate, $locate]);
    }

    /** @return \Closure(): array<int, string> every query run since this was called */
    private function countQueries(): \Closure
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        return function () use (&$queries): array {
            return $queries;
        };
    }
}
