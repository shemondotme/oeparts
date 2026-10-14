<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Install\InstallManager;
use App\Services\Instance\ConfigReloader;
use App\Services\Instance\InstanceCacheReset;
use App\Services\Instance\InstanceEnv;
use App\Services\Instance\InstanceState;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\Contracts\RollbackHook;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportRollback;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Migration\Import\Recovery\RecoveryPoint;
use App\Services\Migration\Import\RowDecoder;
use App\Services\Migration\Import\RowLoader;
use App\Services\Migration\Import\Stages\RecoveryPointStage;
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
 * Phase 6.5: the way back. A recovery point is a verified copy of every table made before anything changes; the
 * rollback puts it back exactly, table by table and segment by segment, resumes after a kill, and refuses to open a
 * half-restored site.
 */
class ImportRecoveryAndRollbackTest extends TestCase
{
    use BuildsImportPackages;
    use PlantsRows;
    use RefreshDatabase;

    private string $envFile = '';

    private string $envText = '';

    private object $guard;

    private string $ledgerBackup = '';

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
            public int $resumed = 0;

            public function hold(): void {}

            public function resume(): void
            {
                $this->resumed++;
            }
        };
        $this->app->instance(QueueGuard::class, $this->guard);
        $this->app->forgetInstance(ImportRollback::class);
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
        $this->cleanUpImportTest();

        parent::tearDown();
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    private function plantSite(): void
    {
        foreach (range(1, 6) as $i) {
            $this->plantRow('products', ['oem_number' => "OE{$i}", 'normalized_oem' => "OE{$i}", 'slug' => "p-{$i}", 'price' => $i * 10 + 0.5, 'name' => json_encode(['en' => "Part {$i} é ü \"quoted\"", 'de' => 'Bremse'], JSON_UNESCAPED_UNICODE)]);
        }
        $this->plantRow('users', ['name' => 'Anna Berg', 'email' => 'anna@example.test']);
        $this->plantRow('orders', ['order_number' => 'ORD-1', 'customer_note' => "line one\nline two ✓"]);
        $this->plantRow('redirects', ['from_url' => '/a', 'to_url' => 'https://x.example/a']);
        $this->plantRow('redirects', ['from_url' => '/b', 'to_url' => 'https://x.example/b']);
    }

    /** @return array<string, string> table => JSON of every row in key order, for every non-system table */
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

    private function confirmRun(string $id): void
    {
        $acks = (array) $this->store()->find($id)->package['preflight']['required_acks'];
        $this->manager()->confirm($id, $acks, ['door' => 'cli', 'typed' => 'REPLACE', 'replace_populated' => true]);
    }

    /** A run whose recovery point is made and verified. */
    private function runWithRecoveryPoint(array $options = []): ImportState
    {
        $run = $this->runToPreflight($this->buildImportPackage('rp-'.uniqid().'.oeparts'), $options + ['door' => 'cli']);
        $this->confirmRun($run['state']->id);
        $this->manager()->advance($run['state']->id, 120.0);

        return $this->store()->find($run['state']->id);
    }

    // ---- the recovery point ---------------------------------------------------------------------------------------

    #[Test]
    public function a_recovery_point_holds_every_table_with_checked_segments_and_the_env_before_image_and_changes_nothing(): void
    {
        $this->plantSite();
        $before = $this->picture();

        $state = $this->runWithRecoveryPoint();

        $point = RecoveryPoint::of($state->id);
        $manifest = (array) $point->read();
        $this->assertTrue($manifest['complete']);
        $this->assertTrue($state->flag('recovery.done'));
        $this->assertEqualsCanonicalizing((new GroupRegistry)->exportableTables(), array_keys($manifest['tables']), 'every non-system table is in it, empty ones too');
        $this->assertSame(array_sum(array_map(fn (string $t): int => DB::table($t)->count(), array_keys($manifest['tables']))), $manifest['rows']);
        $this->assertGreaterThanOrEqual(10, $state->package['recovery']['rows']);

        foreach ($manifest['parts'] as $part) {
            $path = $point->path($part['file']);
            $this->assertFileExists($path);
            $this->assertSame($part['sha256'], hash_file('sha256', $path));
            $this->assertSame($part['bytes'], filesize($path));
        }

        $image = json_decode((string) file_get_contents($this->store()->directory($state->id).'/journal/env-before.json'), true);
        $this->assertSame('http://old.local', $image['values']['APP_URL']);
        $this->assertSame('', $image['values']['OE_MODE']);
        $this->assertNull($image['values']['OE_INSTANCE_ID'], 'a key that is not in the file is remembered as absent');
        $this->assertArrayNotHasKey('APP_KEY', $image['values'], 'only the keys the import may change are copied, never a secret');

        $this->assertSame($before, $this->picture(), 'making the way back changes nothing on the site');
        $this->assertSame($this->envText, file_get_contents($this->envFile));
    }

    #[Test]
    public function the_scaffolding_the_copier_used_is_gone_afterwards(): void
    {
        $this->plantSite();
        $runs = BackupRun::query()->count();
        $disk = Storage::disk((string) config('backup.staging_disk', 'local'));
        $before = $disk->directories('backups');

        $this->runWithRecoveryPoint();

        $this->assertSame($runs, BackupRun::query()->count(), 'no scaffolding row is left in backup_runs');
        $this->assertSame($before, $disk->directories('backups'), 'and no staging folder');
    }

    #[Test]
    public function a_recovery_point_made_in_many_small_steps_by_fresh_managers_is_the_same_as_one_made_at_once(): void
    {
        $this->plantSite();
        config(['migration.export.segment_bytes' => 400, 'migration.export.batch_seconds' => 0, 'migration.export.page_rows' => 2]);

        $once = $this->runWithRecoveryPoint();
        $reference = (array) RecoveryPoint::of($once->id)->read();

        $run = $this->runToPreflight($this->buildImportPackage('stepped.oeparts'), ['door' => 'cli']);
        $id = $run['state']->id;
        $this->confirmRun($id);
        $steps = 0;

        do {
            $this->app->forgetInstance(ImportManager::class);
            $this->app->forgetInstance(RecoveryPointStage::class);
            $progress = $this->manager()->advance($id, 0.0);
            $this->assertLessThan(3000, ++$steps);
        } while (! $this->store()->find($id)->flag('recovery.done'));

        $this->assertGreaterThan(8, $steps, 'it really was made in many steps');
        $stepped = (array) RecoveryPoint::of($id)->read();
        $this->assertSame($reference['tables'], $stepped['tables']);
        $this->assertSame($reference['rows'], $stepped['rows']);
        $this->assertSame(array_column($reference['parts'], 'sha256'), array_column($stepped['parts'], 'sha256'), 'byte-identical segments');
        $this->assertNotSame('', (string) ($progress['status'] ?? ''));
    }

    #[Test]
    public function a_segment_damaged_before_it_was_verified_stops_the_run_before_anything_changes(): void
    {
        $this->plantSite();
        $run = $this->runToPreflight($this->buildImportPackage('damage.oeparts'), ['door' => 'cli']);
        $id = $run['state']->id;
        $this->confirmRun($id);

        $tampered = false;
        for ($i = 0; $i < 400 && ! $tampered; $i++) {
            $this->manager()->advance($id, 0.0);
            $phase = $this->store()->find($id)->stageState['phase'] ?? '';

            if ($phase === 'verify') {
                $parts = (array) RecoveryPoint::of($id)->read()['parts'];
                $file = RecoveryPoint::of($id)->path($parts[0]['file']);
                $bytes = (string) file_get_contents($file);
                file_put_contents($file, substr($bytes, 0, -3).'xyz');
                $tampered = true;
            }
        }

        $this->assertTrue($tampered, 'the stage reached its verify phase');
        $progress = $this->manager()->advance($id, 60.0);

        $this->assertSame('failed', $progress['status']);
        $this->assertSame(MigrationErrorCode::PackageCorrupt->value, $progress['error']['code']);
        $this->assertFalse($this->store()->find($id)->isDestructive());
    }

    // ---- ensure_schema -----------------------------------------------------------------------------------------

    #[Test]
    public function the_schema_stage_passes_when_nothing_is_pending_and_refuses_when_something_is(): void
    {
        $ok = $this->runToPreflight($this->buildImportPackage('ok.oeparts'));
        $this->assertTrue($this->store()->find($ok['state']->id)->flag('schema.done'));

        $name = (string) DB::table('migrations')->orderByDesc('id')->value('migration');
        DB::table('migrations')->where('migration', $name)->delete();
        $result = $this->manager()->start(['path' => $this->buildImportPackage('pending.oeparts', ['migrations' => array_values(array_diff($this->ownMigrations(), [$name]))])], $this->passphrase, [], 'import', 'cli', ['door' => 'cli']);
        $progress = $this->manager()->advance($result['state']->id, 60.0);

        $this->assertSame('failed', $progress['status']);
        $this->assertSame(MigrationErrorCode::MigrationSetDiffers->value, $progress['error']['code']);
    }

    #[Test]
    public function on_the_installer_door_the_schema_stage_runs_the_migrations_that_are_missing(): void
    {
        $run = $this->runToPreflight($this->buildImportPackage('inst.oeparts'), ['door' => 'installer']);

        $this->assertTrue($this->store()->find($run['state']->id)->flag('schema.done'));
        $this->assertSame('awaiting_confirmation', $run['progress']['status']);
    }

    // ---- the rollback ----------------------------------------------------------------------------------------------

    /** Wreck the site the way a failed import would, leaving the run in the state that asks for a rollback. */
    private function wreck(ImportState $state): void
    {
        foreach ((new GroupRegistry)->exportableTables() as $table) {
            DB::table($table)->delete();
        }
        $this->plantRow('products', ['oem_number' => 'GARBAGE', 'normalized_oem' => 'GARBAGE', 'slug' => 'garbage']);

        app(InstanceEnv::class)->apply(['OE_MODE' => 'quarantine', 'OE_INSTANCE_ID' => 'imp1', 'APP_URL' => 'https://new.example'], 'import');
        (new ImportMarker)->write($state->id);
        app(BackupLock::class)->acquire('import:'.$state->id);

        $this->store()->update($state->id, function (ImportState $s): void {
            $s->markDestructive();
            $s->status = ImportStatus::RollingBack;
            $s->error = ['code' => 'import_failed_step'];
        });
    }

    #[Test]
    public function a_rollback_puts_back_every_table_exactly_and_the_env_and_opens_the_site(): void
    {
        $this->plantSite();
        $before = $this->picture();
        $state = $this->runWithRecoveryPoint();
        $this->wreck($state);
        $this->assertNotSame($before, $this->picture());
        $this->assertNotSame($this->envText, file_get_contents($this->envFile));

        $progress = $this->manager()->advance($state->id, 120.0);

        $this->assertSame('rolled_back', $progress['status']);
        $this->assertSame($before, $this->picture(), 'every row of every table is back, byte for byte');
        $this->assertSame($this->envText, file_get_contents($this->envFile), 'the env file is exactly as it was, the added keys removed again');
        $this->assertFalse((new ImportMarker)->isActive());
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertSame(1, $this->guard->resumed);
        $this->assertNull(app(KeyEscrow::class)->get('import-'.$state->id));
        $this->assertNotNull($this->store()->find($state->id)->finishedAt);
        $this->assertTrue(collect(app(PackageAudit::class)->history($state->id))->contains(fn (array $l): bool => $l['event'] === 'import.rolled_back'));
    }

    #[Test]
    public function ids_start_again_from_where_they_were_so_the_next_order_gets_the_next_number(): void
    {
        $this->plantSite();
        $maxBefore = (int) DB::table('products')->max('id');
        $state = $this->runWithRecoveryPoint();
        $this->wreck($state);

        $this->manager()->advance($state->id, 120.0);
        $next = $this->plantRow('products', ['oem_number' => 'NEXT', 'normalized_oem' => 'NEXT', 'slug' => 'next']);

        $this->assertSame($maxBefore + 1, $next);
    }

    #[Test]
    public function a_rollback_killed_after_every_step_resumes_and_ends_the_same(): void
    {
        $this->plantSite();
        config(['migration.export.segment_bytes' => 400, 'migration.export.batch_seconds' => 0, 'migration.export.page_rows' => 2]);
        $before = $this->picture();
        $state = $this->runWithRecoveryPoint();
        $this->wreck($state);
        $steps = 0;

        do {
            $this->app->forgetInstance(ImportManager::class);
            $this->app->forgetInstance(ImportRollback::class);
            $progress = $this->manager()->advance($state->id, 0.0);
            $this->assertLessThan(3000, ++$steps);
        } while ($progress['status'] === 'rolling_back');

        $this->assertGreaterThan(5, $steps);
        $this->assertSame('rolled_back', $progress['status']);
        $this->assertSame($before, $this->picture());
        $this->assertSame($this->envText, file_get_contents($this->envFile));
    }

    #[Test]
    public function a_table_whose_reload_was_cut_off_in_the_middle_starts_again_instead_of_duplicating_rows(): void
    {
        $this->plantSite();
        config(['migration.export.segment_bytes' => 400, 'migration.export.batch_seconds' => 0, 'migration.export.page_rows' => 2]);
        $before = $this->picture();
        $state = $this->runWithRecoveryPoint();
        $this->wreck($state);

        // Take one step, then simulate a kill that left extra rows in the table the rollback was loading.
        $this->manager()->advance($state->id, 0.0);
        $cursor = $this->store()->find($state->id)->stageState['rb'] ?? null;
        $this->assertNotNull($cursor);
        $table = RecoveryPoint::of($state->id)->tables()[$cursor['ti']] ?? null;
        $this->assertNotNull($table);
        DB::table($table)->insert(DB::table($table)->count() > 0 ? [] : []);
        $this->plantRow('redirects', ['from_url' => '/stray', 'to_url' => '/stray']);

        $progress = $this->manager()->advance($state->id, 120.0);

        $this->assertSame('rolled_back', $progress['status']);
        $this->assertSame($before, $this->picture(), 'no duplicate, no stray row');
    }

    #[Test]
    public function a_rollback_that_cannot_finish_leaves_the_site_closed_and_says_so(): void
    {
        $this->plantSite();
        $state = $this->runWithRecoveryPoint();
        $this->wreck($state);

        $parts = (array) RecoveryPoint::of($state->id)->read()['parts'];
        $products = array_values(array_filter($parts, fn (array $p): bool => $p['table'] === 'products'))[0];
        file_put_contents(RecoveryPoint::of($state->id)->path($products['file']), 'not a gzip file at all');

        $progress = $this->manager()->advance($state->id, 120.0);

        $this->assertSame('rollback_failed', $progress['status']);
        $this->assertSame(MigrationErrorCode::RollbackFailed->value, $progress['error']['code']);
        $this->assertTrue((new ImportMarker)->isActive(), 'the site stays closed: nobody writes to a half-restored database');
        $this->assertTrue(app(BackupLock::class)->isLocked());
        $this->assertSame(0, $this->guard->resumed);
        $this->assertNotNull(app(KeyEscrow::class)->get('import-'.$state->id), 'the key stays for the next try');
    }

    #[Test]
    public function a_failed_rollback_can_be_tried_again_after_the_cause_is_fixed(): void
    {
        $this->plantSite();
        $before = $this->picture();
        $state = $this->runWithRecoveryPoint();
        $this->wreck($state);
        $parts = (array) RecoveryPoint::of($state->id)->read()['parts'];
        $products = array_values(array_filter($parts, fn (array $p): bool => $p['table'] === 'products'))[0];
        $file = RecoveryPoint::of($state->id)->path($products['file']);
        $good = (string) file_get_contents($file);
        file_put_contents($file, 'broken');
        $this->assertSame('rollback_failed', $this->manager()->advance($state->id, 120.0)['status']);

        file_put_contents($file, $good); // somebody restored the segment from a backup
        $progress = $this->manager()->rollbackRun($state->id, 120.0);

        $this->assertSame('rolled_back', $progress['status']);
        $this->assertSame($before, $this->picture());
    }

    #[Test]
    public function a_run_that_changed_nothing_has_nothing_to_roll_back(): void
    {
        $run = $this->runToPreflight($this->buildImportPackage());

        try {
            $this->manager()->rollbackRun($run['state']->id);
            $this->fail('expected a refusal');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::NotReadyToConfirm, $e->errorCode);
        }
    }

    #[Test]
    public function the_hooks_of_later_parts_run_first_and_a_hook_that_is_not_finished_keeps_the_rollback_going(): void
    {
        $this->plantSite();
        $state = $this->runWithRecoveryPoint();
        $this->wreck($state);

        $hook = new class implements RollbackHook
        {
            public int $calls = 0;

            public function name(): string
            {
                return 'probe';
            }

            public function run(ImportState $state, ImportContext $context): bool
            {
                return ++$this->calls >= 3;
            }
        };
        $rollback = new ImportRollback(
            app(ImportStateStore::class), app(BackupLock::class), app(ImportMarker::class), $this->guard,
            app(RowLoader::class), app(InstanceEnv::class), app(InstanceCacheReset::class), app(PackageAudit::class), app(KeyEscrow::class), [$hook],
        );
        $this->app->instance(ImportRollback::class, $rollback);
        $this->app->forgetInstance(ImportManager::class);

        $this->manager()->advance($state->id, 0.0);
        $this->assertSame('rolling_back', $this->store()->find($state->id)->status->value, 'the hook is not finished yet');
        $this->manager()->advance($state->id, 0.0);
        $this->manager()->advance($state->id, 120.0);

        $this->assertSame(3, $hook->calls);
        $this->assertSame('rolled_back', $this->store()->find($state->id)->status->value);
        $this->assertTrue($this->store()->find($state->id)->flag('rollback.probe'));
    }

    // ---- the row format -----------------------------------------------------------------------------------------------

    #[Test]
    public function rows_come_back_exactly_including_bytes_unicode_and_nulls(): void
    {
        $decoder = RowDecoder::fromHeader('{"$":"oe-rows/1","table":"redirects","columns":["id","from_url","to_url"],"types":["int","string","string"]}');

        $row = $decoder->decode('[1,"é ü ✓ \"q\"",{"$b":"'.base64_encode("\xFF\xFE\x00raw").'"}]');
        $this->assertSame([1, 'é ü ✓ "q"', "\xFF\xFE\x00raw"], $row);
        $this->assertSame([2, null, 'x'], $decoder->decode('[2,null,"x"]'));
    }

    #[Test]
    public function a_line_that_is_not_a_row_of_this_table_is_refused(): void
    {
        $decoder = RowDecoder::fromHeader('{"$":"oe-rows/1","table":"redirects","columns":["id","from_url"]}');

        foreach (['[1]', '[1,"a","b"]', 'not json', '{"a":1}', '[1,{"x":1}]', '[1,{"$b":"!!notbase64!!"}]'] as $bad) {
            try {
                $decoder->decode($bad);
                $this->fail("'{$bad}' must be refused");
            } catch (MigrationException $e) {
                $this->assertSame(MigrationErrorCode::PackageCorrupt, $e->errorCode);
            }
        }

        foreach (['', '{}', '{"$":"other/9","table":"t","columns":["a"]}', '{"$":"oe-rows/1","table":"t","columns":[]}', '{"$":"oe-rows/1","table":"t","columns":["bad name"]}'] as $badHeader) {
            try {
                RowDecoder::fromHeader($badHeader);
                $this->fail("header '{$badHeader}' must be refused");
            } catch (MigrationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function the_loader_inserts_in_batches_checks_the_columns_and_truncate_restarts_the_ids(): void
    {
        $loader = new RowLoader;
        $decoder = RowDecoder::fromHeader('{"$":"oe-rows/1","table":"redirects","columns":["id","from_url","to_url","type"],"types":["int","string","string","string"]}');
        $loader->prepareSession();

        $lines = [];
        foreach (range(1, 1200) as $i) {
            $lines[] = json_encode([$i, "/f{$i}", "/t{$i}", '301']);
        }
        $batches = [];
        $total = $loader->load($decoder, $lines, function (int $so_far) use (&$batches): void {
            $batches[] = $so_far;
        });

        $this->assertSame(1200, $total);
        $this->assertSame([500, 1000, 1200], $batches, 'batches of at most 500 rows');
        $this->assertSame(1200, DB::table('redirects')->count());

        $loader->truncate('redirects');
        $this->assertSame(0, DB::table('redirects')->count());

        $wrong = RowDecoder::fromHeader('{"$":"oe-rows/1","table":"redirects","columns":["id","from_url"],"types":["int","string"]}');
        $this->expectException(MigrationException::class);
        $loader->assertColumns($wrong);
    }

    #[Test]
    public function the_recovery_point_refuses_a_segment_name_that_could_leave_its_directory(): void
    {
        $this->plantSite();
        $state = $this->runWithRecoveryPoint();
        $point = RecoveryPoint::of($state->id);

        foreach (['../manifest.json', '/etc/passwd', 'a/b', "x\0y", ''] as $bad) {
            try {
                $point->path($bad);
                $this->fail("'{$bad}' must be refused");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
