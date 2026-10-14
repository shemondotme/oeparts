<?php

namespace Tests\Feature;

use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\FulltextIndex;
use App\Services\Migration\Import\FulltextRollbackHook;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportStageRegistry;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\RowLoader;
use App\Services\Migration\Import\Stages\DbIndexDropStage;
use App\Services\Migration\Import\Stages\DbIndexRestoreStage;
use App\Services\Migration\Import\Stages\DbSeedDefaultsStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationException;
use Database\Seeders\CarriersSeeder;
use Database\Seeders\SectionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsImportPackages;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 6.6c: the search index dropped for the load and built again after, and the default data of the groups that were
 * not imported. The index statements are recorded by a stand-in (the real DDL is MySQL-only and proven in the MySQL suite).
 */
class ImportIndexAndSeedTest extends TestCase
{
    use BuildsImportPackages;
    use PlantsRows;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareImportTest();
        (new ImportMarker)->remove();
    }

    protected function tearDown(): void
    {
        (new ImportMarker)->remove();
        $this->cleanUpImportTest();

        parent::tearDown();
    }

    // ---- helpers ---------------------------------------------------------------------------------------------

    private function fakeIndex(bool $present = true, bool $wanted = true, bool $supported = true): FulltextIndex
    {
        return new class($present, $wanted, $supported) extends FulltextIndex
        {
            /** @var array<int, string> */
            public array $calls = [];

            public ?\Closure $during = null;

            public bool $failNextDrop = false;

            public function __construct(public bool $present, private readonly bool $wanted, private readonly bool $supported) {}

            public function supported(): bool
            {
                return $this->supported;
            }

            public function dropWanted(): bool
            {
                return $this->wanted;
            }

            public function exists(): bool
            {
                return $this->present;
            }

            public function drop(): void
            {
                $this->calls[] = 'drop';
                ($this->during)?->__invoke();

                if ($this->failNextDrop) {
                    $this->failNextDrop = false;

                    throw new \RuntimeException('connection lost');
                }

                $this->present = false;
            }

            public function add(): void
            {
                $this->calls[] = 'add';
                ($this->during)?->__invoke();
                $this->present = true;
            }
        };
    }

    private function armedState(array $flags = ['clear.done'], array $options = []): ImportState
    {
        $state = new ImportState('idx'.bin2hex(random_bytes(6)), options: $options);
        foreach ($flags as $flag) {
            $state->setFlag($flag);
        }
        $state->package['groups'] = ['core', 'catalog'];
        (new ImportMarker)->write($state->id);
        (new ImportStateStore)->save($state);

        return $state;
    }

    private function context(float $seconds = 60.0): ImportContext
    {
        return new ImportContext(microtime(true) + $seconds, app(KeyEscrow::class));
    }

    private function dropStage(FulltextIndex $index): DbIndexDropStage
    {
        return new DbIndexDropStage($index, new ImportMarker, new ImportStateStore);
    }

    private function restoreStage(FulltextIndex $index): DbIndexRestoreStage
    {
        return new DbIndexRestoreStage($index, new ImportStateStore);
    }

    private function savedPackage(ImportState $state): array
    {
        return (new ImportStateStore)->find($state->id)->package;
    }

    // ---- the registry ----------------------------------------------------------------------------------------

    #[Test]
    public function the_stages_run_in_the_order_of_the_plan(): void
    {
        $this->assertSame(
            ['locate', 'verify', 'ensure_schema', 'preflight', 'recovery_point', 'arm', 'db_clear', 'db_index_drop', 'db_data', 'db_index_restore', 'db_audit', 'db_seed_defaults', 'files', 'fin_secrets', 'fin_check_migrations', 'fin_rewrite', 'fin_normalize', 'fin_origin_reset', 'fin_sequences', 'fin_caches', 'fin_admins', 'fin_sessions', 'fin_links', 'fin_sitemap', 'fin_queue', 'fin_backup_key', 'fin_readiness', 'complete'],
            app(ImportStageRegistry::class)->keys('import')
        );
    }

    // ---- dropping and rebuilding the search index ---------------------------------------------------------------

    #[Test]
    public function with_the_default_strategy_or_on_a_server_without_the_index_nothing_is_dropped(): void
    {
        foreach ([[true, false, true], [true, true, false], [false, true, true]] as [$present, $wanted, $supported]) {
            $index = $this->fakeIndex($present, $wanted, $supported);
            $state = $this->armedState();

            $this->dropStage($index)->step($state, $this->context());

            $this->assertSame([], $index->calls);
            $this->assertTrue($state->flag('index.drop.done'));
            $this->assertArrayNotHasKey('indexes_dropped', $state->package, 'nothing to build again afterwards');
        }
    }

    #[Test]
    public function the_real_index_class_on_this_test_database_has_nothing_to_drop(): void
    {
        $index = new FulltextIndex;

        $this->assertFalse($index->supported());
        $this->assertSame('drop_readd', config('migration.import.fulltext_strategy'), 'measured at 1M products: loading with the index in place is more than twice as slow');
    }

    #[Test]
    public function the_drop_is_written_to_the_state_before_the_statement_runs_and_the_long_wait_is_announced(): void
    {
        config(['migration.import.long_stage_seconds' => 900]);
        $index = $this->fakeIndex();
        $state = $this->armedState();
        $seen = [];
        $index->during = function () use ($state, &$seen): void {
            $saved = (new ImportStateStore)->find($state->id);
            $seen = ['package' => $saved->package['indexes_dropped'] ?? null, 'silence' => $saved->longRunningUntil];
        };

        $this->dropStage($index)->step($state, $this->context());

        $this->assertSame([$index->name()], $seen['package'], 'the record that the index may be gone was already on disk when the DDL began');
        $this->assertNotNull($seen['silence'], 'and so was the announcement of a long silent statement');
        $this->assertNull($state->longRunningUntil, 'the silence is over once the statement ends');
        $this->assertSame(['drop'], $index->calls);
        $this->assertTrue($state->flag('index.drop.done'));
    }

    #[Test]
    public function a_stage_that_dies_before_the_drop_finished_drops_it_on_the_next_call_and_after_the_drop_does_not_drop_twice(): void
    {
        $index = $this->fakeIndex();
        $state = $this->armedState();
        $index->failNextDrop = true;

        try {
            $this->dropStage($index)->step($state, $this->context());
            $this->fail('the first attempt fails');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([$index->name()], $this->savedPackage($state)['indexes_dropped'], 'the rollback would know');

        $resumed = (new ImportStateStore)->find($state->id);
        $this->dropStage($index)->step($resumed, $this->context());
        $this->assertSame(['drop', 'drop'], $index->calls, 'the index was still there, so it is dropped now');

        // A process killed AFTER the DDL but before the flag was saved: the server says it is gone, so it counts as done.
        $killed = $this->armedState();
        $killed->package['indexes_dropped'] = [$index->name()];
        $index->present = false;
        $index->calls = [];
        $this->dropStage($index)->step($killed, $this->context());
        $this->assertSame([], $index->calls);
        $this->assertTrue($killed->flag('index.drop.done'));
    }

    #[Test]
    public function the_index_is_built_again_only_when_it_was_dropped_and_only_when_it_is_not_already_there(): void
    {
        $never = $this->fakeIndex();
        $this->restoreStage($never)->step($state = $this->armedState(['index.drop.done']), $this->context());
        $this->assertSame([], $never->calls);
        $this->assertTrue($state->flag('index.restore.done'));

        $index = $this->fakeIndex(present: false);
        $dropped = $this->armedState(['index.drop.done']);
        $dropped->package['indexes_dropped'] = [$index->name()];
        $this->restoreStage($index)->step($dropped, $this->context());
        $this->assertSame(['add'], $index->calls);
        $this->assertTrue($index->present);
        $this->assertNull($dropped->longRunningUntil);

        // Killed while MySQL was still building it: the next call finds the index and does not start a second build.
        $again = $this->armedState(['index.drop.done']);
        $again->package['indexes_dropped'] = [$index->name()];
        $index->calls = [];
        $this->restoreStage($index)->step($again, $this->context());
        $this->assertSame([], $index->calls);
        $this->assertTrue($again->flag('index.restore.done'));
    }

    #[Test]
    public function the_rollback_builds_the_index_again_whether_or_not_the_drop_finished(): void
    {
        $index = $this->fakeIndex(present: false);
        $hook = new FulltextRollbackHook($index, new ImportStateStore);

        $untouched = $this->armedState();
        $this->assertTrue($hook->run($untouched, $this->context()));
        $this->assertSame([], $index->calls, 'an import that never dropped it has nothing to put back');

        $dropped = $this->armedState();
        $dropped->package['indexes_dropped'] = [$index->name()];
        $this->assertTrue($hook->run($dropped, $this->context()));
        $this->assertSame(['add'], $index->calls);

        // The drop was announced but never ran: the index is still there, and the hook leaves it alone.
        $announced = $this->armedState();
        $announced->package['indexes_dropped'] = [$index->name()];
        $index->calls = [];
        $this->assertTrue($hook->run($announced, $this->context()));
        $this->assertSame([], $index->calls);
        $this->assertSame('index', $hook->name());
    }

    #[Test]
    public function the_drop_refuses_to_run_before_the_old_data_is_cleared(): void
    {
        $index = $this->fakeIndex();

        $this->expectException(MigrationException::class);
        $this->dropStage($index)->step($this->armedState([]), $this->context());
    }

    // ---- default data of the groups that were left out ---------------------------------------------------------

    private function seedStage(): DbSeedDefaultsStage
    {
        return new DbSeedDefaultsStage(new GroupRegistry, app(RowLoader::class), new ImportMarker, new ImportStateStore);
    }

    #[Test]
    public function the_seed_backed_groups_are_the_ones_the_installer_really_seeds(): void
    {
        $this->assertSame(['content' => ['SectionsSeeder'], 'shipping_tax' => ['CarriersSeeder']], config('migration.seed_backed'));
        $this->assertContains('sections', (new GroupRegistry)->tablesOf([GroupKey::Content]));
        $this->assertContains('carriers', (new GroupRegistry)->tablesOf([GroupKey::ShippingTax]));
    }

    #[Test]
    public function a_group_left_out_of_the_import_ends_with_exactly_what_a_fresh_install_seeds_and_nothing_of_the_old_site(): void
    {
        DB::table('sections')->delete();
        DB::table('carriers')->delete();
        $this->seed(SectionsSeeder::class);
        $this->seed(CarriersSeeder::class);
        $baseline = ['sections' => DB::table('sections')->count(), 'carriers' => DB::table('carriers')->count()];
        $this->assertGreaterThan(0, $baseline['sections']);

        // The old site's leftovers in both groups, which an import that left them out must not keep.
        $this->plantRow('sections', ['type' => 'leftover-'.uniqid(), 'location' => 'homepage']);
        $this->plantRow('carriers', ['name' => 'Old courier']);

        $state = $this->armedState(['clear.done', 'audit.done'], ['groups' => ['core', 'catalog']]);
        $result = $this->seedStage()->step($state, $this->context());

        $this->assertTrue($result->done);
        $this->assertTrue($state->flag('seed.done'));
        $this->assertSame($baseline['sections'], DB::table('sections')->count(), 'the same rows a fresh installer run leaves');
        $this->assertSame($baseline['carriers'], DB::table('carriers')->count());
        $this->assertSame(0, DB::table('carriers')->where('name', 'Old courier')->count());
        $this->assertSame($baseline['sections'], $state->package['seeded']['content']['sections']);
        $this->assertSame($baseline['carriers'], $state->package['seeded']['shipping_tax']['carriers']);
    }

    #[Test]
    public function a_group_that_was_imported_is_never_touched(): void
    {
        DB::table('carriers')->delete();
        $this->plantRow('carriers', ['name' => 'Imported courier']);
        DB::table('sections')->delete();

        $state = $this->armedState(['clear.done', 'audit.done'], ['groups' => ['core', 'shipping_tax']]);
        $this->seedStage()->step($state, $this->context());

        $this->assertSame(['Imported courier'], DB::table('carriers')->pluck('name')->all(), 'shipping_tax came from the package: not truncated, not seeded');
        $this->assertGreaterThan(0, DB::table('sections')->count(), 'content was left out: it is seeded');
        $this->assertArrayNotHasKey('shipping_tax', $state->package['seeded']);
    }

    #[Test]
    public function the_seeding_resumes_one_group_per_step_and_ends_the_same(): void
    {
        DB::table('sections')->delete();
        DB::table('carriers')->delete();
        $state = $this->armedState(['clear.done', 'audit.done'], ['groups' => ['core']]);

        $first = $this->seedStage()->step($state, new ImportContext(microtime(true) - 1, app(KeyEscrow::class)));
        $this->assertFalse($first->done, 'time ran out after the first group');
        $this->assertSame(1, $state->stageState['gi']);

        $resumed = (new ImportStateStore)->find($state->id);
        $second = $this->seedStage()->step($resumed, $this->context());

        $this->assertTrue($second->done);
        $this->assertEqualsCanonicalizing(['content', 'shipping_tax'], array_keys($resumed->package['seeded']));
        $this->assertGreaterThan(0, DB::table('sections')->count());
        $this->assertGreaterThan(0, DB::table('carriers')->count());
    }

    #[Test]
    public function the_seeding_refuses_to_run_before_the_audit_or_without_the_marker(): void
    {
        $unchecked = $this->armedState(['clear.done']);

        try {
            $this->seedStage()->step($unchecked, $this->context());
            $this->fail('expected a refusal');
        } catch (MigrationException) {
            $this->addToAssertionCount(1);
        }

        $open = $this->armedState(['clear.done', 'audit.done']);
        (new ImportMarker)->remove();
        $this->expectException(MigrationException::class);
        $this->seedStage()->step($open, $this->context());
    }
}
