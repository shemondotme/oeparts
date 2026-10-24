<?php

namespace Tests\Feature;

use App\Services\Instance\InstanceState;
use App\Services\Instance\Readiness\Checks\DataChecks;
use App\Services\Instance\Readiness\Checks\IdentityChecks;
use App\Services\Instance\Readiness\Checks\ModeChecks;
use App\Services\Instance\Readiness\Checks\RuntimeChecks;
use App\Services\Instance\Readiness\Checks\SecretChecks;
use App\Services\Instance\Readiness\ReadinessCheck;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Instance\Readiness\ReadinessRegistry;
use App\Services\Instance\Readiness\ReadinessReport;
use App\Services\Instance\Readiness\ReadinessRunner;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\Stages\FinReadinessStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsImportPackages;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 7.3: the launch-readiness report: its shape, the runner that carries it in steps, and the static checks that need
 * nothing but the data and the settings.
 */
class ReadinessTest extends TestCase
{
    use BuildsImportPackages;
    use PlantsRows;
    use RefreshDatabase;

    private string $runnerFile = '';

    private string $ledgerBackup = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareImportTest();
        (new ImportMarker)->remove();
        $this->runnerFile = $this->workDir.'/readiness.json';

        $ledger = InstanceState::file()->path();
        $this->ledgerBackup = is_file($ledger) ? (string) file_get_contents($ledger) : '';
        @unlink($ledger);
        @unlink($ledger.'.prev');
    }

    protected function tearDown(): void
    {
        (new ImportMarker)->remove();
        $ledger = InstanceState::file()->path();
        @unlink($ledger);
        @unlink($ledger.'.prev');

        if ($this->ledgerBackup !== '') {
            file_put_contents($ledger, $this->ledgerBackup);
        }

        $this->cleanUpImportTest();

        parent::tearDown();
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    private function context(?ImportState $import = null, ?string $host = null): ReadinessContext
    {
        return ReadinessContext::make($import, $host);
    }

    private function importWith(array $package): ImportState
    {
        $state = new ImportState('rdy'.bin2hex(random_bytes(6)));
        $state->package = $package;
        (new ImportStateStore)->save($state);

        return $state;
    }

    private function setting(string $group, string $key, ?string $value, string $type = 'string', bool $encrypted = false): void
    {
        DB::table('settings')->updateOrInsert(['group' => $group, 'key' => $key], ['value' => $value, 'type' => $type, 'is_encrypted' => $encrypted, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function probe(string $key, string $group, string $status, bool $overridable = false): ReadinessProbe
    {
        return new ReadinessProbe($key, $group, fn (ReadinessContext $c): ReadinessCheck => new ReadinessCheck($key, $key, $group, $status, '', [], '', $overridable));
    }

    private function runner(ReadinessProbe ...$probes): ReadinessRunner
    {
        return new ReadinessRunner(new ReadinessRegistry($probes), $this->runnerFile);
    }

    // ---- the report ---------------------------------------------------------------------------------------------

    #[Test]
    public function a_site_can_be_released_only_when_nothing_fails_nothing_is_pending_and_the_report_is_complete(): void
    {
        $make = fn (array $checks, bool $complete = true): ReadinessReport => new ReadinessReport($checks, $complete, now()->toIso8601String());
        $c = fn (string $key, string $status, bool $overridable = false): ReadinessCheck => new ReadinessCheck($key, $key, 'x', $status, overridable: $overridable);

        $this->assertTrue($make([$c('a', 'pass'), $c('b', 'warn'), $c('c', 'skip')])->canRelease());
        $this->assertFalse($make([$c('a', 'pass'), $c('b', 'fail')])->canRelease());
        $this->assertFalse($make([$c('a', 'pass'), $c('b', 'pending')])->canRelease(), 'not knowing yet is not a yes');
        $this->assertFalse($make([$c('a', 'pass')], complete: false)->canRelease(), 'a report that is still being made cannot release anything');
    }

    #[Test]
    public function only_an_overridable_failure_can_be_waived_and_only_by_its_own_key(): void
    {
        $report = new ReadinessReport([
            new ReadinessCheck('storage_link', 'x', 'runtime', 'fail', overridable: true),
            new ReadinessCheck('rows_vs_manifest', 'x', 'data', 'fail'),
        ], true, now()->toIso8601String());

        $this->assertFalse($report->canRelease(['storage_link']), 'a data failure can never be waived');
        $this->assertFalse($report->canRelease(['rows_vs_manifest']), 'and asking for it changes nothing');

        $only = new ReadinessReport([new ReadinessCheck('storage_link', 'x', 'runtime', 'fail', overridable: true)], true, now()->toIso8601String());
        $this->assertTrue($only->canRelease(['storage_link']));
        $this->assertFalse($only->canRelease(['something_else']));
    }

    #[Test]
    public function the_report_survives_being_saved_and_read_back(): void
    {
        $report = new ReadinessReport([new ReadinessCheck('a', 'A', 'data', 'warn', 'm', ['n' => 1], 'do this', true)], true, '2026-10-14T10:00:00+00:00');

        $back = ReadinessReport::fromArray($report->toArray());

        $this->assertSame($report->toArray(), $back->toArray());
        $this->assertSame(1, $back->counts()['warn']);
    }

    // ---- the runner ---------------------------------------------------------------------------------------------

    #[Test]
    public function the_runner_does_one_check_per_step_and_a_fresh_runner_carries_on(): void
    {
        $probes = [$this->probe('one', 'data', 'pass'), $this->probe('two', 'data', 'warn'), $this->probe('three', 'identity', 'pass')];
        $this->runner(...$probes)->reset();
        $keys = [];

        for ($i = 0; $i < 3; $i++) {
            $report = $this->runner(...$probes)->advance($this->context(), 0.0); // a new runner each time: a new process
            $keys[] = count($report->checks);
        }

        $this->assertSame([1, 2, 3], $keys);
        $this->assertTrue($report->complete);
        $this->assertSame(['one', 'two', 'three'], array_map(fn (ReadinessCheck $c): string => $c->key, $report->checks));
        $this->assertSame(3, count($this->runner(...$probes)->last()->checks), 'what it found is on disk');
    }

    #[Test]
    public function a_check_that_throws_is_a_failure_with_a_reason_and_does_not_hide_the_others(): void
    {
        $broken = new ReadinessProbe('broken', 'data', function (): ReadinessCheck {
            throw new \RuntimeException('the table is gone');
        });

        $report = $this->runner($this->probe('before', 'data', 'pass'), $broken, $this->probe('after', 'data', 'pass'))->run($this->context());

        $this->assertCount(3, $report->checks);
        $this->assertSame('fail', $report->find('broken')->status);
        $this->assertStringContainsString('the table is gone', $report->find('broken')->message);
        $this->assertSame('pass', $report->find('after')->status);
    }

    #[Test]
    public function a_finished_run_starts_again_from_the_first_check_the_next_time(): void
    {
        $runner = $this->runner($this->probe('one', 'data', 'pass'));
        $runner->run($this->context());

        $again = $runner->advance($this->context(), 0.0);

        $this->assertCount(1, $again->checks, 'a new run, not the old one repeated');
    }

    #[Test]
    public function the_applications_own_list_covers_every_group_in_the_order_data_identity_secrets_mode_runtime(): void
    {
        $keys = (new ReadinessRegistry)->keys();

        $this->assertSame(['rows_vs_manifest', 'fk_integrity_sample', 'file_counts_checksums', 'migrations_current', 'default_language', 'maintenance_off'], array_slice($keys, 0, 6));
        $this->assertContains('sequences', $keys);
        $this->assertContains('secrets_reentry', $keys);
        $this->assertContains('mode_self_test', $keys);
        $this->assertContains('origin_switches', $keys);
        $this->assertSame($keys, array_values(array_unique($keys)), 'no check is listed twice');
    }

    // ---- data -------------------------------------------------------------------------------------------------

    #[Test]
    public function the_row_counts_are_compared_with_what_the_package_carried(): void
    {
        $checks = new DataChecks;
        $this->assertSame('skip', $checks->rowsVsManifest($this->context())->status, 'no import, nothing to compare');

        $this->plantRow('redirects', ['from_url' => '/a', 'to_url' => '/b']);
        $this->plantRow('redirects', ['from_url' => '/c', 'to_url' => '/d']);

        $this->assertSame('pass', $checks->rowsVsManifest($this->context($this->importWith(['loaded' => ['redirects' => 2]])))->status);

        $fewer = $checks->rowsVsManifest($this->context($this->importWith(['loaded' => ['redirects' => 5]])));
        $this->assertSame('fail', $fewer->status);
        $this->assertSame(['redirects' => ['expected' => 5, 'found' => 2]], $fewer->meta['tables']);

        $more = $checks->rowsVsManifest($this->context($this->importWith(['loaded' => ['redirects' => 1]])));
        $this->assertSame('warn', $more->status, 'rows added since are only worth a look');
    }

    #[Test]
    public function a_reference_to_a_missing_row_is_found_in_the_sample(): void
    {
        $checks = new DataChecks;
        $this->assertSame('pass', $checks->fkIntegritySample($this->context())->status);

        $this->plantRow('products', ['oem_number' => 'ORPHAN1', 'normalized_oem' => 'ORPHAN1', 'slug' => 'orphan-1', 'manufacturer_id' => 987654]);

        $found = $checks->fkIntegritySample($this->context());

        $this->assertSame('warn', $found->status);
        $this->assertNotEmpty(array_filter(array_keys($found->meta['references']), fn (string $s): bool => str_starts_with($s, 'products.manufacturer_id->manufacturers.id')));
    }

    #[Test]
    public function the_files_the_journal_names_must_be_on_the_disk(): void
    {
        $state = $this->importWith([]);
        $journal = (new ImportStateStore)->directory($state->id).'/journal';
        mkdir($journal, 0775, true);
        $present = storage_path('app/public/readiness-probe-'.uniqid().'.txt');
        file_put_contents($present, 'x');
        $relative = basename($present);

        try {
            file_put_contents($journal.'/files-created.ndjson', json_encode(['d' => 'public', 'p' => $relative])."\n");
            $this->assertSame('pass', (new DataChecks)->files($this->context($state))->status);

            file_put_contents($journal.'/files-created.ndjson', json_encode(['d' => 'public', 'p' => $relative])."\n".json_encode(['d' => 'public', 'p' => 'gone/'.uniqid().'.jpg'])."\n");
            $missing = (new DataChecks)->files($this->context($state));
            $this->assertSame('fail', $missing->status);
            $this->assertSame(1, $missing->meta['missing']);
        } finally {
            @unlink($present);
        }

        $this->assertSame('skip', (new DataChecks)->files($this->context($this->importWith([])))->status, 'a site that received no files has nothing to check');
    }

    #[Test]
    public function the_schema_the_language_and_maintenance_are_looked_at(): void
    {
        $checks = new DataChecks;
        $this->assertSame('pass', $checks->migrationsCurrent($this->context())->status);

        $name = (string) DB::table('migrations')->orderByDesc('id')->value('migration');
        DB::table('migrations')->where('migration', $name)->delete();
        $this->assertSame('fail', $checks->migrationsCurrent($this->context())->status);

        DB::table('languages')->delete();
        $this->assertSame('fail', $checks->defaultLanguage($this->context())->status, 'the incident where the default language was deleted');
        $this->plantRow('languages', ['code' => 'en', 'is_active' => 1, 'is_default' => 1]);
        $this->assertSame('pass', $checks->defaultLanguage($this->context())->status);

        $this->setting('maintenance', 'enabled', '1', 'boolean');
        $this->assertSame('fail', $checks->maintenanceOff($this->context())->status);
        $this->setting('maintenance', 'enabled', '0', 'boolean');
        $this->assertSame('pass', $checks->maintenanceOff($this->context())->status);
    }

    // ---- identity ---------------------------------------------------------------------------------------------

    #[Test]
    public function the_site_address_must_agree_with_itself(): void
    {
        $checks = new IdentityChecks;
        config(['app.url' => 'https://shop.example']);
        $this->setting('general', 'site_url', 'https://shop.example');
        $this->assertSame('pass', $checks->appUrl($this->context())->status);

        $this->setting('general', 'site_url', 'https://other.example');
        $this->assertSame('fail', $checks->appUrl($this->context())->status);

        config(['app.url' => '']);
        $this->assertSame('fail', $checks->appUrl($this->context())->status);
    }

    #[Test]
    public function a_request_to_another_host_is_a_failure_on_the_real_shop_and_a_warning_on_a_copy(): void
    {
        $checks = new IdentityChecks;
        config(['app.url' => 'https://shop.example']);

        $this->assertSame('skip', $checks->hostMatches($this->context())->status, 'from the command line there is no host asked for');
        $this->assertSame('pass', $checks->hostMatches($this->context(host: 'SHOP.example'))->status);

        config(['instance.mode' => null]);
        $this->assertSame('fail', $checks->hostMatches($this->context(host: 'clone.example'))->status);

        config(['instance.mode' => 'staging']);
        $this->assertSame('warn', $checks->hostMatches($this->context(host: 'clone.example'))->status);
    }

    #[Test]
    public function the_canonical_host_must_be_empty_or_the_sites_own(): void
    {
        $checks = new IdentityChecks;
        config(['app.url' => 'https://shop.example']);

        $this->setting('seo', 'canonical_host', '');
        $this->assertSame('pass', $checks->canonicalHost($this->context())->status);
        $this->setting('seo', 'canonical_host', 'shop.example');
        $this->assertSame('pass', $checks->canonicalHost($this->context())->status);
        $this->setting('seo', 'canonical_host', 'old.example');
        $this->assertSame('fail', $checks->canonicalHost($this->context())->status, 'visitors would be sent to the old site');
    }

    #[Test]
    public function a_counter_behind_the_issued_numbers_fails_because_the_first_order_would_collide(): void
    {
        $checks = new IdentityChecks;
        $ym = now()->format('Ym');
        DB::table('sequences')->updateOrInsert(['type' => 'order'], ['current_value' => 3, 'resets_monthly' => 1, 'last_reset_month' => now()->format('Y-m'), 'created_at' => now(), 'updated_at' => now()]);
        $this->plantRow('orders', ['order_number' => "ORD-{$ym}-000050"]);

        $behind = $checks->sequences($this->context());

        $this->assertSame('fail', $behind->status);
        $this->assertSame(['value' => 3, 'should_be' => 50], $behind->meta['series']['order']);

        DB::table('sequences')->where('type', 'order')->update(['current_value' => 50]);
        $this->assertSame('pass', $checks->sequences($this->context())->status);
    }

    // ---- secrets, mode, runtime --------------------------------------------------------------------------------------

    #[Test]
    public function a_secret_this_site_cannot_open_is_a_failure_and_is_named(): void
    {
        $this->setting('mail', 'smtp_password', (new Encrypter(random_bytes(32), 'AES-256-CBC'))->encryptString('x'), 'encrypted', true);

        $check = (new SecretChecks)->secrets($this->context());

        $this->assertSame('fail', $check->status);
        $this->assertSame(['mail.smtp_password'], $check->meta['settings']);
    }

    #[Test]
    public function a_copy_proves_it_holds_back_what_it_should_and_the_real_shop_has_nothing_to_prove(): void
    {
        $checks = new ModeChecks;

        config(['instance.mode' => null]);
        $this->assertSame('skip', $checks->selfTest($this->context())->status);

        foreach (['staging', 'quarantine'] as $mode) {
            config(['instance.mode' => $mode]);
            $this->assertSame('pass', $checks->selfTest($this->context())->status, $mode);
        }
    }

    #[Test]
    public function a_copy_needs_a_cache_prefix_of_its_own(): void
    {
        $checks = new ModeChecks;
        config(['instance.mode' => 'quarantine', 'instance.id' => 'iabc123', 'cache.prefix' => 'oe_iabc123_', 'cache.default' => 'array']);
        $this->assertSame('pass', $checks->cacheIsolation($this->context())->status);

        config(['cache.prefix' => '']);
        $this->assertSame('fail', $checks->cacheIsolation($this->context())->status);

        config(['cache.prefix' => 'oe_iabc123_', 'cache.default' => 'redis']);
        $this->assertSame('warn', $checks->cacheIsolation($this->context())->status, 'shared server, own prefix: fine but worth knowing');

        config(['instance.mode' => null]);
        $this->assertSame('skip', $checks->cacheIsolation($this->context())->status);
    }

    #[Test]
    public function the_runtime_checks_look_at_administrators_the_scheduler_the_link_and_the_switches(): void
    {
        $checks = new RuntimeChecks;

        DB::table('admins')->delete();
        $this->assertSame('fail', $checks->adminLogin($this->context())->status);
        $this->plantAdmin(['email' => 'root@site.test']);
        $this->assertSame('pass', $checks->adminLogin($this->context())->status);

        Cache::forget('scheduler_heartbeat');
        $this->assertSame('warn', $checks->schedulerHeartbeat($this->context())->status);
        $justImported = $this->importWith([]);
        $this->assertSame('pending', $checks->schedulerHeartbeat($this->context($justImported))->status, 'the scheduler has not had time yet');
        Cache::put('scheduler_heartbeat', now()->toIso8601String(), 120);
        $this->assertSame('pass', $checks->schedulerHeartbeat($this->context($justImported))->status);

        config(['app.url' => 'http://shop.example']);
        $this->assertSame('warn', $checks->schemeSecurity($this->context())->status);
        config(['app.url' => 'https://shop.example']);
        $this->assertSame('pass', $checks->schemeSecurity($this->context())->status);
        config(['app.url' => 'http://localhost']);
        $this->assertSame('pass', $checks->schemeSecurity($this->context())->status);

        $link = $checks->storageLink($this->context());
        $this->assertTrue(in_array($link->status, ['pass', 'fail'], true));
        $this->assertSame($link->status === 'fail', $link->overridable, 'a missing link is the one runtime failure a person may waive');
    }

    #[Test]
    public function the_import_switched_off_list_is_reported_until_each_one_is_back_on(): void
    {
        $checks = new RuntimeChecks;
        (new InstanceState)->update(function (array $ledger): array {
            $ledger['import'] = ['switched_off' => ['backup.schedule_enabled', 'cleanup.auto_enabled']];

            return $ledger;
        });
        $this->setting('backup', 'schedule_enabled', '0', 'boolean');
        $this->setting('cleanup', 'auto_enabled', '0', 'boolean');

        $off = $checks->originSwitches($this->context());
        $this->assertSame('warn', $off->status);
        $this->assertSame(['backup.schedule_enabled', 'cleanup.auto_enabled'], $off->meta['settings']);

        $this->setting('backup', 'schedule_enabled', '1', 'boolean');
        $this->setting('cleanup', 'auto_enabled', '1', 'boolean');
        $this->assertSame('pass', $checks->originSwitches($this->context())->status);
    }

    // ---- the command and the import's own stage -------------------------------------------------------------------

    #[Test]
    public function the_command_exits_with_three_when_something_blocks_the_launch_and_zero_when_nothing_does(): void
    {
        $this->app->instance(ReadinessRegistry::class, new ReadinessRegistry([$this->probe('ok', 'data', 'pass'), $this->probe('bad', 'data', 'fail')]));
        $this->app->forgetInstance(ReadinessRunner::class);
        $this->assertSame(3, Artisan::call('oeparts:instance:readiness'));
        $this->assertStringContainsString('Something blocks the launch.', Artisan::output());

        $this->app->instance(ReadinessRegistry::class, new ReadinessRegistry([$this->probe('ok', 'data', 'pass'), $this->probe('meh', 'runtime', 'warn')]));
        $this->app->forgetInstance(ReadinessRunner::class);
        $this->assertSame(0, Artisan::call('oeparts:instance:readiness'));
        $this->assertSame(9, Artisan::call('oeparts:instance:readiness', ['--strict' => true]), 'warnings fail only under --strict');
    }

    #[Test]
    public function the_command_can_print_the_report_as_json(): void
    {
        $this->app->instance(ReadinessRegistry::class, new ReadinessRegistry([$this->probe('ok', 'data', 'pass')]));
        $this->app->forgetInstance(ReadinessRunner::class);

        Artisan::call('oeparts:instance:readiness', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame('ok', $report['checks'][0]['key']);
        $this->assertSame(1, $report['counts']['pass']);
        $this->assertTrue($report['complete']);
    }

    private function stageState(): ImportState
    {
        $state = new ImportState('rds'.bin2hex(random_bytes(6)));
        $state->setFlag('fin.backup_key.done');
        (new ImportMarker)->write($state->id);
        (new ImportStateStore)->save($state);

        return $state;
    }

    #[Test]
    public function the_import_stops_and_takes_itself_back_on_a_data_failure_but_only_notes_the_rest(): void
    {
        $context = new ImportContext(microtime(true) + 60, app(KeyEscrow::class));

        $this->app->instance(ReadinessRegistry::class, new ReadinessRegistry([$this->probe('fine', 'data', 'pass'), $this->probe('worker', 'runtime', 'fail')]));
        $this->app->forgetInstance(ReadinessRunner::class);
        $this->app->forgetInstance(FinReadinessStage::class);
        $state = $this->stageState();

        $this->assertTrue(app(FinReadinessStage::class)->step($state, $context)->done, 'a runtime failure is for the owner to see, not a reason to undo the import');
        $this->assertSame(['worker'], $state->package['readiness']['failed']);
        $this->assertSame([], $state->package['readiness']['blocking']);

        $this->app->instance(ReadinessRegistry::class, new ReadinessRegistry([$this->probe('rows_vs_manifest', 'data', 'fail')]));
        $this->app->forgetInstance(ReadinessRunner::class);
        $this->app->forgetInstance(FinReadinessStage::class);

        $this->expectException(MigrationException::class);
        app(FinReadinessStage::class)->step($this->stageState(), $context);
    }
}
