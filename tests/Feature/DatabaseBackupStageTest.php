<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\Stages\DatabaseBackupStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DatabaseBackupStage (Module 21, Chunk 2.2) — the first concrete BackupStage.
 *
 * Drives the stage directly (one chunk per step) over a controlled table, and
 * once end-to-end through the BackupManager. Asserts observable output — part
 * rows, keyset chunking, structure-only excludes, and real decompressed SQL —
 * never merely "no error".
 */
class DatabaseBackupStageTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;
    private string $filesRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-db-stage-test-'.getmypid();
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
        config(['backup.disk' => 'local']);

        // The full-profile manager test also runs the file stage — point it at an
        // empty dir so it doesn't back up (and encrypt) the whole project tree.
        $this->filesRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-db-stage-files-'.getmypid();
        @mkdir($this->filesRoot, 0775, true);
        config(['backup.files.root' => $this->filesRoot]);

        // A small, deterministic table to assert against.
        Schema::create('oe_bk_widget', function ($t) {
            $t->id();
            $t->string('name');
        });

        DB::table('oe_bk_widget')->insert([
            ['name' => 'alpha'], ['name' => 'bravo'], ['name' => 'charlie'],
            ['name' => 'delta'], ['name' => 'echo'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('oe_bk_widget');
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);
        @array_map('unlink', glob($this->filesRoot.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->filesRoot);

        parent::tearDown();
    }

    /** Drive the stage to completion, returning every part attrs it emitted. */
    private function drive(DatabaseBackupStage $stage, BackupRun $run): array
    {
        $state = [];
        $parts = [];
        $guard = 0;

        do {
            $result = $stage->step($run, $state);
            // ->parts holds the earlier units from this (possibly batched) step,
            // in chronological order; ->part holds the last one — append in that
            // order so a batched step's parts stay chronological here too.
            foreach ($result->parts as $partAttrs) {
                $parts[] = $partAttrs;
            }
            if ($result->part !== null) {
                $parts[] = $result->part;
            }
            $state = $result->state;
        } while (! $result->done && ++$guard < 100000);

        return $parts;
    }

    private function newRun(): BackupRun
    {
        return BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL,
            'status'  => BackupRun::STATUS_RUNNING,
            'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk'    => 'local',
        ]);
    }

    #[Test]
    public function it_dumps_schema_then_keyset_paged_data_parts(): void
    {
        config(['backup.db.chunk_rows' => 2]);

        $parts = $this->drive(new DatabaseBackupStage(), $this->newRun());
        $mine  = array_values(array_filter($parts, fn ($p) => $p['name'] === 'oe_bk_widget'));

        $schema = array_values(array_filter($mine, fn ($p) => $p['meta']['kind'] === 'schema'));
        $data   = array_values(array_filter($mine, fn ($p) => $p['meta']['kind'] === 'data'));

        $this->assertCount(1, $schema, 'one schema part per table');
        // 5 rows @ 2/page = 3 data chunks (2, 2, 1).
        $this->assertCount(3, $data);
        $this->assertSame([2, 2, 1], array_map(fn ($p) => $p['rows'], $data));
        $this->assertSame(5, array_sum(array_map(fn ($p) => $p['rows'], $data)));

        // Parts are real files, checksummed.
        Storage::disk('local')->assertExists($schema[0]['path']);
        $this->assertNotNull($schema[0]['sha256']);
    }

    #[Test]
    public function data_parts_contain_restorable_insert_sql(): void
    {
        config(['backup.db.chunk_rows' => 100]);

        $parts = $this->drive(new DatabaseBackupStage(), $this->newRun());
        $data  = array_values(array_filter(
            $parts,
            fn ($p) => $p['name'] === 'oe_bk_widget' && $p['meta']['kind'] === 'data'
        ));

        $this->assertCount(1, $data);

        $sql = gzdecode(Storage::disk('local')->get($data[0]['path']));

        $this->assertStringContainsString('INSERT INTO `oe_bk_widget`', $sql);
        $this->assertStringContainsString("'alpha'", $sql);
        $this->assertStringContainsString("'echo'", $sql);
    }

    #[Test]
    public function a_schema_part_holds_a_create_table_statement(): void
    {
        $parts  = $this->drive(new DatabaseBackupStage(), $this->newRun());
        $schema = array_values(array_filter(
            $parts,
            fn ($p) => $p['name'] === 'oe_bk_widget' && $p['meta']['kind'] === 'schema'
        ));

        $sql = gzdecode(Storage::disk('local')->get($schema[0]['path']));

        $this->assertStringContainsString('DROP TABLE IF EXISTS `oe_bk_widget`', $sql);
        $this->assertStringContainsString('oe_bk_widget', $sql);
    }

    #[Test]
    public function excluded_tables_are_dumped_structure_only(): void
    {
        config(['backup.db.exclude_table_data' => ['oe_bk_widget']]);

        $parts = $this->drive(new DatabaseBackupStage(), $this->newRun());
        $mine  = array_values(array_filter($parts, fn ($p) => $p['name'] === 'oe_bk_widget'));

        $this->assertCount(1, $mine, 'only the schema part, no data');
        $this->assertSame('schema', $mine[0]['meta']['kind']);
    }

    #[Test]
    public function it_backs_up_tables_without_a_single_column_pk_via_offset(): void
    {
        // Composite-PK table — keyColumn() returns null → OFFSET fallback path.
        Schema::create('oe_bk_pivot', function ($t) {
            $t->unsignedBigInteger('a');
            $t->unsignedBigInteger('b');
            $t->primary(['a', 'b']);
        });
        DB::table('oe_bk_pivot')->insert([
            ['a' => 1, 'b' => 1], ['a' => 1, 'b' => 2], ['a' => 2, 'b' => 1],
        ]);

        config(['backup.db.chunk_rows' => 2]);

        $parts = $this->drive(new DatabaseBackupStage(), $this->newRun());
        $data  = array_values(array_filter(
            $parts,
            fn ($p) => $p['name'] === 'oe_bk_pivot' && $p['meta']['kind'] === 'data'
        ));

        $this->assertSame(3, array_sum(array_map(fn ($p) => $p['rows'], $data)), 'all 3 pivot rows captured');
        $this->assertNull($data[0]['meta']['key'], 'offset mode records a null key');

        Schema::dropIfExists('oe_bk_pivot');
    }

    #[Test]
    public function the_manager_runs_the_db_stage_end_to_end(): void
    {
        config(['backup.stages.full' => [DatabaseBackupStage::class]]);
        config(['backup.db.chunk_rows' => 2]);

        $run = app(BackupManager::class)->start(BackupRun::PROFILE_FULL);
        $run = app(BackupManager::class)->run($run);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $this->assertGreaterThan(0, $run->part_count);

        $widgetData = $run->parts()
            ->where('name', 'oe_bk_widget')
            ->where('meta->kind', 'data')
            ->sum('rows');
        $this->assertSame(5, (int) $widgetData);

        // The manifest indexes every part and is self-describing.
        $manifest = app(BackupManifest::class)->read($run);
        $this->assertNotNull($manifest);
        $this->assertSame($run->part_count, count($manifest['parts']));
    }
}
