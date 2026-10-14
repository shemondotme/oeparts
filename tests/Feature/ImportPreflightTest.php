<?php

namespace Tests\Feature;

use App\Services\Backup\BackupLock;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Migration\Import\LedgerComparison;
use App\Services\Migration\Import\MigrationLedgerReconciler;
use App\Services\Migration\Import\TargetClass;
use App\Services\Migration\Import\TargetFreshness;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsImportPackages;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 6.4: the checks before an import, the classification of the site that would be replaced, the migration rule
 * R2, and the confirmation gate. One test per check key, and the confirmation refuses everything it should.
 */
class ImportPreflightTest extends TestCase
{
    use BuildsImportPackages;
    use PlantsRows;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareImportTest();
        app(BackupLock::class)->release();
        config(['migration.import.recovery_max_rows' => 500_000, 'queue.default' => 'sync']);
    }

    protected function tearDown(): void
    {
        app(BackupLock::class)->release();
        $this->cleanUpImportTest();

        parent::tearDown();
    }

    private function statusOf(array $checks, string $key): string
    {
        $this->assertArrayHasKey($key, $checks, "no check named {$key}");

        return $checks[$key]['status'];
    }

    // ---- a good package on a clean site ---------------------------------------------------------------------------

    #[Test]
    public function a_good_package_on_an_unused_site_passes_every_check_and_waits_for_confirmation(): void
    {
        $run = $this->runToPreflight($this->buildImportPackage());

        $this->assertSame('awaiting_confirmation', $run['progress']['status']);
        $preflight = $run['state']->package['preflight'];
        $failed = array_filter($preflight['checks'], fn (array $c): bool => $c['status'] === 'fail');

        $this->assertSame([], array_column($failed, 'key'), 'nothing may fail on a clean site');
        $this->assertTrue($preflight['can_proceed']);
        $this->assertSame([], $preflight['required_acks']);
        $this->assertTrue($run['state']->flag('preflight.done'));

        foreach (['package', 'format_version', 'app_version', 'migration_ledger', 'php_version', 'extensions', 'db_engine', 'privileges', 'memory', 'target_state', 'recovery_point', 'disk', 'lock_free', 'app_key', 'writable', 'admin_login_possible', 'package_identity', 'queue_guard', 'pending_jobs', 'launch_mode', 'import_eta'] as $key) {
            $this->assertArrayHasKey($key, $this->checksOf($run['state']), $key);
        }
    }

    #[Test]
    public function the_checks_change_nothing_not_even_leave_the_probe_table_behind(): void
    {
        $tables = Schema::getTableListing();
        $queriesChanging = [];
        DB::listen(function ($q) use (&$queriesChanging): void {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $q->sql) === 1 && ! str_contains($q->sql, 'oe_import_probe_') && ! str_contains($q->sql, 'sqlite_sequence')) {
                $queriesChanging[] = $q->sql;
            }
        });

        $this->runToPreflight($this->buildImportPackage());

        $this->assertEqualsCanonicalizing($tables, Schema::getTableListing(), 'the probe table is dropped again');
        $this->assertSame([], $queriesChanging, 'no row of the site was written');
    }

    // ---- the package ----------------------------------------------------------------------------------------------

    #[Test]
    public function a_package_from_a_newer_release_than_this_site_is_refused(): void
    {
        $version = $this->ownVersion();
        if ($version === null) {
            $this->markTestSkipped('no version.json');
        }

        $run = $this->runToPreflight($this->buildImportPackage('newer.oeparts', ['app_version' => '99.0.0']));

        $this->assertSame('fail', $this->statusOf($this->checksOf($run['state']), 'app_version'));
        $this->assertFalse($run['state']->package['preflight']['can_proceed']);
    }

    #[Test]
    public function a_major_version_jump_is_a_warning_that_needs_an_acknowledgement(): void
    {
        $version = $this->ownVersion();
        if ($version === null || (int) explode('.', $version)[0] < 1) {
            $this->markTestSkipped('needs a version with a major above zero');
        }

        $older = ((int) explode('.', $version)[0] - 1).'.0.0';
        $run = $this->runToPreflight($this->buildImportPackage('older.oeparts', ['app_version' => $older]));

        $checks = $this->checksOf($run['state']);
        $this->assertSame('warn', $this->statusOf($checks, 'app_version'));
        $this->assertSame('version_major_jump', $checks['app_version']['ack']);
        $this->assertContains('version_major_jump', $run['state']->package['preflight']['required_acks']);
        $this->assertTrue($run['state']->package['preflight']['can_proceed'], 'a warning does not block');
    }

    #[Test]
    public function a_different_set_of_migrations_is_a_hard_failure_in_either_direction(): void
    {
        $own = $this->ownMigrations();

        $behind = $this->runToPreflight($this->buildImportPackage('ahead.oeparts', ['migrations' => [...$own, '2099_01_01_000000_from_the_future']]));
        $checks = $this->checksOf($behind['state']);
        $this->assertSame('fail', $this->statusOf($checks, 'migration_ledger'));
        $this->assertStringContainsString('same release', $checks['migration_ledger']['message']);

        $ahead = $this->runToPreflight($this->buildImportPackage('behind.oeparts', ['migrations' => array_slice($own, 0, -3)]));
        $this->assertSame('fail', $this->statusOf($this->checksOf($ahead['state']), 'migration_ledger'));
    }

    #[Test]
    public function a_package_that_carries_no_migration_list_is_refused(): void
    {
        $run = $this->runToPreflight($this->buildImportPackage('empty.oeparts', ['migrations' => []]));

        $checks = $this->checksOf($run['state']);
        $this->assertSame('fail', $this->statusOf($checks, 'migration_ledger'));
        $this->assertStringContainsString('does not say', $checks['migration_ledger']['message']);
    }

    #[Test]
    public function the_reconciler_compares_the_sets_and_the_versions(): void
    {
        $this->assertInstanceOf(LedgerComparison::class, $same = (new MigrationLedgerReconciler)->compare($this->ownMigrations(), '2.1.0', '2.1.0'));
        $this->assertTrue($same->identical());
        $this->assertFalse($same->versionsDiffer());

        $older = (new MigrationLedgerReconciler)->compare(array_slice($this->ownMigrations(), 0, -1), '1.9.0', '2.1.0');
        $this->assertTrue($older->targetIsNewer());
        $this->assertTrue($older->isMajorJump());
        $this->assertFalse($older->packageIsNewerRelease());

        $newer = (new MigrationLedgerReconciler)->compare([...$this->ownMigrations(), 'x_new'], '2.2.0', '2.1.0');
        $this->assertTrue($newer->targetIsOlder());
        $this->assertTrue($newer->packageIsNewerRelease());
        $this->assertFalse($newer->isMajorJump());
        $this->assertSame(['x_new'], $newer->toArray()['missing_in_target']);

        $this->assertTrue((new MigrationLedgerReconciler)->compare([], null, null)->packageLedgerIsEmpty());
    }

    #[Test]
    public function the_package_identity_is_checked_again_by_the_preflight(): void
    {
        $path = $this->buildImportPackage();
        $result = $this->manager()->start(['path' => $path], $this->passphrase);
        $this->manager()->advance($result['state']->id, 60.0); // verify passes; preflight not yet run
        $this->store()->update($result['state']->id, fn (ImportState $s) => $s->stage = 'preflight');
        $this->store()->update($result['state']->id, fn (ImportState $s) => $s->status = ImportStatus::Running);
        touch($path, time() + 400);

        $this->manager()->advance($result['state']->id, 60.0);

        $this->assertSame('fail', $this->statusOf($this->checksOf($this->store()->find($result['state']->id)), 'package_identity'));
    }

    // ---- who can sign in afterwards ---------------------------------------------------------------------------------

    #[Test]
    public function a_package_with_no_active_super_admin_that_could_sign_in_is_refused_unless_an_extra_admin_is_given(): void
    {
        $inactive = [['id' => 1, 'name' => 'Off', 'email' => 'x@old.example', 'password' => '$2y$04$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'is_active' => 0, 'role' => 'super_admin']];
        $plain = [['id' => 1, 'name' => 'Plain', 'email' => 'x@old.example', 'password' => 'not-a-bcrypt-hash', 'is_active' => 1, 'role' => 'super_admin']];
        $support = [['id' => 1, 'name' => 'Staff', 'email' => 'x@old.example', 'password' => '$2y$04$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'is_active' => 1, 'role' => 'support']];

        foreach (['inactive.oeparts' => $inactive, 'plain.oeparts' => $plain, 'support.oeparts' => $support, 'none.oeparts' => []] as $name => $admins) {
            $run = $this->runToPreflight($this->buildImportPackage($name, [], $admins));
            $this->assertSame('fail', $this->statusOf($this->checksOf($run['state']), 'admin_login_possible'), $name);
        }

        $rescued = $this->runToPreflight($this->buildImportPackage('rescued.oeparts', [], []), ['extra_admin' => ['email' => 'me@new.example']]);
        $this->assertSame('pass', $this->statusOf($this->checksOf($rescued['state']), 'admin_login_possible'));
    }

    // ---- what the target holds ------------------------------------------------------------------------------------------

    #[Test]
    public function the_target_is_empty_fresh_or_populated(): void
    {
        $freshness = new TargetFreshness;
        $this->assertSame(TargetClass::Empty, $freshness->assess()->class);

        $this->plantRow('redirects', ['from_url' => '/a', 'to_url' => '/b']);
        $this->assertSame(TargetClass::Fresh, $freshness->assess()->class, 'a redirect lives in the content group, which is NOT core, so this is fresh');
    }

    #[Test]
    public function content_without_business_activity_is_fresh_and_a_row_of_business_activity_makes_it_populated(): void
    {
        $freshness = new TargetFreshness;

        $this->plantRow('products', ['oem_number' => 'A1', 'normalized_oem' => 'A1']);
        $assessment = $freshness->assess();
        $this->assertSame(TargetClass::Fresh, $assessment->class);
        $this->assertFalse($assessment->exceedsRecoveryCap());
        $this->assertTrue($assessment->class->installerMayReplace());

        foreach (['orders', 'users', 'contact_messages', 'newsletter_subscribers'] as $table) {
            $this->plantRow($table);
            $this->assertSame(TargetClass::Populated, $freshness->assess()->class, "a row in {$table} means the site was used");
            $this->assertArrayHasKey($table, $freshness->assess()->businessRows);
            DB::table($table)->delete();
        }

        $this->assertFalse(TargetClass::Populated->installerMayReplace());
    }

    #[Test]
    public function a_site_with_more_rows_than_the_recovery_point_may_hold_is_populated_and_over_the_cap(): void
    {
        config(['migration.import.recovery_max_rows' => 3]);
        foreach (range(1, 5) as $i) {
            $this->plantRow('products', ['oem_number' => "B{$i}", 'normalized_oem' => "B{$i}"]);
        }

        $assessment = (new TargetFreshness)->assess();

        $this->assertSame(TargetClass::Populated, $assessment->class);
        $this->assertTrue($assessment->exceedsRecoveryCap());
        $this->assertGreaterThan(3, $assessment->totalRows);
    }

    #[Test]
    public function a_populated_target_warns_with_the_row_counts_and_needs_an_acknowledgement(): void
    {
        $this->plantRow('orders');
        $this->plantRow('users');

        $run = $this->runToPreflight($this->buildImportPackage());

        $checks = $this->checksOf($run['state']);
        $this->assertSame('warn', $this->statusOf($checks, 'target_state'));
        $this->assertSame('target_populated', $checks['target_state']['ack']);
        $this->assertStringContainsString('1 orders', $checks['target_state']['message']);
        $this->assertSame('populated', $checks['target_state']['meta']['class']);
        $this->assertContains('target_populated', $run['state']->package['preflight']['required_acks']);
    }

    #[Test]
    public function the_installer_door_refuses_a_used_site_while_the_admin_door_and_the_command_line_do_not(): void
    {
        $this->plantRow('orders');
        $path = $this->buildImportPackage();

        $installer = $this->runToPreflight($path, ['door' => 'installer']);
        $this->assertSame('fail', $this->statusOf($this->checksOf($installer['state']), 'target_state'));

        foreach (['admin', 'cli'] as $door) {
            $run = $this->runToPreflight($path, ['door' => $door]);
            $this->assertSame('warn', $this->statusOf($this->checksOf($run['state']), 'target_state'), $door);
        }
    }

    #[Test]
    public function a_target_above_the_recovery_cap_fails_whatever_its_class_and_says_what_raising_it_costs(): void
    {
        config(['migration.import.recovery_max_rows' => 2]);
        foreach (range(1, 4) as $i) {
            $this->plantRow('products', ['oem_number' => "C{$i}", 'normalized_oem' => "C{$i}"]);
        }

        $run = $this->runToPreflight($this->buildImportPackage());

        $check = $this->checksOf($run['state'])['recovery_point'];
        $this->assertSame('fail', $check['status']);
        $this->assertStringContainsString('Raise migration.import.recovery_max_rows', $check['message']);
        $this->assertStringContainsString('disk', $check['message']);
        $this->assertFalse($run['state']->package['preflight']['can_proceed']);
    }

    // ---- this server ---------------------------------------------------------------------------------------------------

    #[Test]
    public function not_enough_disk_fails_with_the_numbers(): void
    {
        config(['migration.import.min_free_bytes' => PHP_INT_MAX / 4]);

        $run = $this->runToPreflight($this->buildImportPackage());

        $check = $this->checksOf($run['state'])['disk'];
        $this->assertSame('fail', $check['status']);
        $this->assertStringContainsString('free', $check['message']);
    }

    #[Test]
    public function another_run_holding_the_shared_lock_fails_the_checks(): void
    {
        app(BackupLock::class)->acquire('backup:9');

        $run = $this->runToPreflight($this->buildImportPackage());

        $this->assertSame('fail', $this->statusOf($this->checksOf($run['state']), 'lock_free'));
    }

    #[Test]
    public function queued_jobs_and_a_workerless_queue_are_warnings(): void
    {
        config(['queue.default' => 'database']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);

        $run = $this->runToPreflight($this->buildImportPackage());

        $checks = $this->checksOf($run['state']);
        $this->assertSame('warn', $this->statusOf($checks, 'pending_jobs'));
        $this->assertSame('warn', $this->statusOf($checks, 'queue_guard'));
        $this->assertTrue($run['state']->package['preflight']['can_proceed']);
    }

    #[Test]
    public function the_launch_mode_is_explained(): void
    {
        $live = $this->runToPreflight($this->buildImportPackage(), ['launch' => 'live']);
        $staging = $this->runToPreflight($this->buildImportPackage('s.oeparts'), ['launch' => 'staging']);

        $this->assertStringContainsString('live', $this->checksOf($live['state'])['launch_mode']['message']);
        $this->assertStringContainsString('staging', $this->checksOf($staging['state'])['launch_mode']['message']);
    }

    // ---- confirm() ------------------------------------------------------------------------------------------------------

    private function ready(array $options = []): ImportState
    {
        return $this->runToPreflight($this->buildImportPackage('ready-'.uniqid().'.oeparts'), $options)['state'];
    }

    private function refusal(callable $do): MigrationErrorCode
    {
        try {
            $do();
        } catch (MigrationException $e) {
            return $e->errorCode;
        }

        $this->fail('the confirmation must be refused');
    }

    #[Test]
    public function confirming_with_everything_in_order_freezes_the_options_and_lets_the_run_go_on(): void
    {
        $state = $this->ready(['door' => 'cli']);

        $confirmed = $this->manager()->confirm($state->id, [], ['door' => 'cli', 'typed' => 'REPLACE', 'options' => ['launch' => 'staging']]);

        $this->assertTrue($confirmed->flag('confirmed'));
        $this->assertSame(ImportStatus::Running, $confirmed->status);
        $this->assertSame('staging', $confirmed->options['launch']);
        $this->assertSame('cli', $confirmed->options['door']);
        $this->assertArrayHasKey('confirmed_at', $confirmed->options);
    }

    #[Test]
    public function a_run_that_is_not_waiting_cannot_be_confirmed(): void
    {
        $result = $this->manager()->start(['path' => $this->buildImportPackage()], $this->passphrase);

        $this->assertSame(MigrationErrorCode::NotReadyToConfirm, $this->refusal(fn () => $this->manager()->confirm($result['state']->id, [], ['typed' => 'REPLACE'])));
        $this->assertSame(MigrationErrorCode::NotReadyToConfirm, $this->refusal(fn () => $this->manager()->confirm('0123456789abcdef', [], [])));
    }

    #[Test]
    public function a_failed_check_blocks_the_confirmation(): void
    {
        $run = $this->runToPreflight($this->buildImportPackage('bad.oeparts', ['migrations' => []]));

        $this->assertSame(MigrationErrorCode::PreflightFailed, $this->refusal(fn () => $this->manager()->confirm($run['state']->id, [], ['door' => 'cli', 'typed' => 'REPLACE'])));
        $this->assertFalse($this->store()->find($run['state']->id)->flag('confirmed'));
    }

    #[Test]
    public function every_acknowledgement_the_checks_asked_for_is_required_by_name(): void
    {
        $this->plantRow('orders');
        $state = $this->ready();

        $code = null;
        try {
            $this->manager()->confirm($state->id, [], ['door' => 'cli', 'typed' => 'REPLACE', 'replace_populated' => true]);
        } catch (MigrationException $e) {
            $code = $e->errorCode;
            $this->assertSame('target_populated', $e->params['what']);
        }

        $this->assertSame(MigrationErrorCode::AcknowledgementMissing, $code);

        $this->manager()->confirm($state->id, ['target_populated'], ['door' => 'cli', 'typed' => 'REPLACE', 'replace_populated' => true]);
        $this->assertTrue($this->store()->find($state->id)->flag('confirmed'));
    }

    #[Test]
    public function the_admin_door_needs_the_word_replace_typed_exactly(): void
    {
        $state = $this->ready();

        foreach ([null, '', 'replace', 'REPLACE ', 'yes'] as $typed) {
            $this->assertSame(MigrationErrorCode::ReplaceNotConfirmed, $this->refusal(fn () => $this->manager()->confirm($state->id, [], ['door' => 'admin', 'typed' => $typed])), (string) $typed);
        }

        $this->manager()->confirm($state->id, [], ['door' => 'admin', 'typed' => 'REPLACE']);
        $this->assertTrue($this->store()->find($state->id)->flag('confirmed'));
    }

    #[Test]
    public function the_command_line_needs_replace_and_for_a_populated_site_also_replace_populated(): void
    {
        $this->plantRow('orders');
        $state = $this->ready();

        $this->assertSame(MigrationErrorCode::ReplaceNotConfirmed, $this->refusal(fn () => $this->manager()->confirm($state->id, ['target_populated'], ['door' => 'cli', 'typed' => 'REPLACE'])), 'REPLACE alone is not enough for a used site');
        $this->assertSame(MigrationErrorCode::ReplaceNotConfirmed, $this->refusal(fn () => $this->manager()->confirm($state->id, ['target_populated'], ['door' => 'cli', 'replace_populated' => true])), 'the flag alone is not enough either');

        $this->manager()->confirm($state->id, ['target_populated'], ['door' => 'cli', 'typed' => 'REPLACE', 'replace_populated' => true]);
        $this->assertTrue($this->store()->find($state->id)->flag('confirmed'));
    }

    #[Test]
    public function a_script_written_for_fresh_installs_cannot_wipe_a_used_site_by_accident(): void
    {
        $this->plantRow('users');
        $state = $this->ready();

        $this->assertSame(MigrationErrorCode::ReplaceNotConfirmed, $this->refusal(fn () => $this->manager()->confirm($state->id, ['target_populated'], ['door' => 'cli', 'typed' => 'REPLACE'])));
    }

    #[Test]
    public function a_package_touched_after_the_checks_cannot_be_confirmed(): void
    {
        $path = $this->buildImportPackage('touch.oeparts');
        $state = $this->runToPreflight($path)['state'];
        touch($path, time() + 900);

        $this->assertSame(MigrationErrorCode::PackageChanged, $this->refusal(fn () => $this->manager()->confirm($state->id, [], ['door' => 'cli', 'typed' => 'REPLACE'])));
    }

    #[Test]
    public function a_site_that_gained_business_records_after_the_checks_cannot_be_confirmed_on_the_old_report(): void
    {
        $state = $this->ready();
        $this->plantRow('orders'); // somebody ordered between the checks and the go

        $this->assertSame(MigrationErrorCode::NotReadyToConfirm, $this->refusal(fn () => $this->manager()->confirm($state->id, [], ['door' => 'cli', 'typed' => 'REPLACE'])));
    }

    #[Test]
    public function another_job_holding_the_lock_after_the_checks_blocks_the_confirmation(): void
    {
        $state = $this->ready();
        app(BackupLock::class)->acquire('backup:5');

        $this->assertSame(MigrationErrorCode::LockBusy, $this->refusal(fn () => $this->manager()->confirm($state->id, [], ['door' => 'cli', 'typed' => 'REPLACE'])));
    }

    #[Test]
    public function after_confirmation_the_checks_are_not_run_again_and_before_it_they_can_be(): void
    {
        $state = $this->ready();
        $first = $state->package['preflight']['at'];

        $again = $this->manager()->rerunPreflight($state->id);
        $this->assertSame('awaiting_confirmation', $again['status']);

        $this->manager()->confirm($state->id, [], ['door' => 'cli', 'typed' => 'REPLACE']);
        $afterConfirm = $this->store()->find($state->id)->package['preflight']['at'];
        $this->manager()->advance($state->id, 30.0);

        $this->assertSame($afterConfirm, $this->store()->find($state->id)->package['preflight']['at'], 'a confirmed run is not re-judged');
        $this->assertSame(MigrationErrorCode::NotReadyToConfirm, $this->refusal(fn () => $this->manager()->rerunPreflight($state->id)));
        $this->assertNotNull($first);
    }

    // ---- the dry run --------------------------------------------------------------------------------------------------------

    #[Test]
    public function a_dry_run_ends_complete_with_the_checks_and_a_plan_and_changes_nothing(): void
    {
        $this->plantRow('products', ['oem_number' => 'D1', 'normalized_oem' => 'D1']);
        $before = DB::table('products')->count();
        $path = $this->buildImportPackage();

        $run = $this->runToPreflight($path, [], 'dry_run');

        $this->assertSame('complete', $run['progress']['status']);
        $plan = $run['state']->package['plan'];
        $this->assertEqualsCanonicalizing(['settings' => 1, 'products' => 1, 'admins' => 1], $plan['tables']);
        $this->assertSame(3, $plan['rows']);
        $this->assertSame(1, $plan['files']);
        $this->assertSame('old.example', $plan['source']['domain']);
        $this->assertNotEmpty($run['state']->package['preflight']['checks']);
        $this->assertSame($before, DB::table('products')->count());
        $this->assertFalse($run['state']->isDestructive());
    }

    #[Test]
    public function the_stage_list_of_a_dry_run_is_the_real_one_up_to_preflight_plus_the_plan(): void
    {
        $registry = app(ImportStageRegistry::class);

        $this->assertSame(['locate', 'verify', 'preflight'], $registry->keys('import'));
        $this->assertSame(['locate', 'verify', 'preflight', 'dry_run_plan'], $registry->keys('dry_run'));
    }
}
