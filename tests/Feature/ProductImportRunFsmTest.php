<?php

namespace Tests\Feature;

use App\Enums\BulkUpdateAction;
use App\Models\Admin;
use App\Models\BulkUpdateLog;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImportRun;
use App\Services\Imports\Exceptions\ImportException;
use App\Services\Imports\ImportManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bulk Product Import redesign — the chunked, resumable FSM (mirrors
 * BackupEngineCoreTest's approach: exercise the real engine against real
 * stages, assert observable state, not just "no error").
 */
class ProductImportRunFsmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['imports.disk' => 'local']);
        config(['imports.batch_size' => 2]); // small, to force multi-step chunking with a tiny fixture
    }

    private function manager(): ImportManager
    {
        return app(ImportManager::class);
    }

    private function seedCatalog(): void
    {
        Manufacturer::factory()->create(['slug' => 'bmw']);
        Condition::create([
            'name' => 'New', 'slug' => 'new',
            'bg_color' => '#DCFCE7', 'text_color' => '#166534',
            'is_active' => true, 'sort_order' => 0,
        ]);
    }

    private function putCsv(string $path, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('local')->put($path, $csv);

        return $path;
    }

    private function fiveRowCsv(): string
    {
        return $this->putCsv('imports/test.csv', array_merge(
            [['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock']],
            array_map(fn (int $i) => ["OEM{$i}", 'bmw', 'new', '10.00', '1'], range(1, 5)),
        ));
    }

    #[Test]
    public function it_processes_a_small_file_across_multiple_chunked_polls(): void
    {
        $this->seedCatalog();
        $admin = Admin::factory()->create();
        $path  = $this->fiveRowCsv();

        $run = $this->manager()->start($path, 'local', 'test.csv', $admin->id, false);
        $this->assertSame(ProductImportRun::STATUS_RUNNING, $run->status);

        $finished = $this->manager()->run($run);

        $this->assertSame(ProductImportRun::STATUS_SUCCESS, $finished->status);
        $this->assertSame(5, $finished->created_count);
        $this->assertSame(0, $finished->updated_count);
        $this->assertSame(0, $finished->skipped_count);
        $this->assertSame(0, $finished->error_count);
        $this->assertSame(5, $finished->total_rows);
        $this->assertSame(5, $finished->processed_rows);
        $this->assertSame(5, Product::count());
        $this->assertNotNull($finished->finished_at);
    }

    #[Test]
    public function advance_performs_exactly_one_chunk_and_persists_a_checkpoint(): void
    {
        $this->seedCatalog();
        $admin = Admin::factory()->create();
        $path  = $this->fiveRowCsv();

        $run = $this->manager()->start($path, 'local', 'test.csv', $admin->id, false);

        // Step 1: ValidateHeaderStage completes in one call, moves to the next stage.
        $this->manager()->advance($run->refresh());
        $run->refresh();
        $this->assertSame(5, $run->total_rows);
        $this->assertSame(1, $run->checkpoint()['stage_index']);

        // Step 2: ImportRowsStage's first call reads exactly batch_size=2 rows.
        $this->manager()->advance($run->refresh());
        $run->refresh();
        $this->assertSame(2, $run->processed_rows);
        $this->assertSame(2, $run->created_count);
        $this->assertSame(1, $run->checkpoint()['stage_index'], 'still mid-stage, not advanced yet');
        $this->assertNotEmpty($run->checkpoint()['stage_state']);

        // Step 3: next batch of 2.
        $this->manager()->advance($run->refresh());
        $run->refresh();
        $this->assertSame(4, $run->processed_rows);

        // Step 4: final row (5th) — a short read (1 < batch_size) signals EOF, stage completes.
        $this->manager()->advance($run->refresh());
        $run->refresh();
        $this->assertSame(5, $run->processed_rows);
        $this->assertSame(2, $run->checkpoint()['stage_index']);

        // Step 5: all stages consumed -> finalize.
        $this->manager()->advance($run->refresh());
        $run->refresh();
        $this->assertSame(ProductImportRun::STATUS_SUCCESS, $run->status);
    }

    #[Test]
    public function a_run_resumes_from_its_persisted_checkpoint_after_a_simulated_crash(): void
    {
        $this->seedCatalog();
        $admin = Admin::factory()->create();
        $path  = $this->putCsv('imports/test.csv', array_merge(
            [['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock']],
            array_map(fn (int $i) => ["OEM{$i}", 'bmw', 'new', '10.00', '1'], range(1, 6)),
        ));

        $run = $this->manager()->start($path, 'local', 'test.csv', $admin->id, false);
        $this->manager()->advance($run->refresh()); // validate header
        $this->manager()->advance($run->refresh()); // first batch of 2
        $this->assertSame(2, $run->refresh()->processed_rows);

        // "Browser closed" — a completely fresh model + fresh manager resolve, nothing in memory.
        $freshRun = ProductImportRun::find($run->id);
        $finished = app(ImportManager::class)->run($freshRun);

        $this->assertSame(ProductImportRun::STATUS_SUCCESS, $finished->status);
        $this->assertSame(6, $finished->processed_rows);
        $this->assertSame(6, $finished->created_count);
    }

    #[Test]
    public function a_second_concurrent_import_is_rejected_and_leaves_no_orphan_row(): void
    {
        $admin = Admin::factory()->create();
        $path  = $this->putCsv('imports/one.csv', [
            ['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock'],
        ]);

        $this->manager()->start($path, 'local', 'one.csv', $admin->id, false);

        $this->expectException(ImportException::class);

        try {
            $this->manager()->start($path, 'local', 'two.csv', $admin->id, false);
        } finally {
            $this->assertSame(1, ProductImportRun::count(), 'the blocked start() must not persist a dangling row');
        }
    }

    #[Test]
    public function row_level_errors_are_captured_without_stopping_the_run_and_the_true_count_is_never_hidden(): void
    {
        $this->seedCatalog();
        $admin = Admin::factory()->create();
        $path  = $this->putCsv('imports/test.csv', [
            ['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock'],
            ['OEM1', 'bmw', 'new', '10.00', '1'],
            ['OEM2', 'does-not-exist', 'new', '10.00', '1'], // bad manufacturer slug
            ['OEM3', 'bmw', 'new', '10.00', '1'],
        ]);

        $run      = $this->manager()->start($path, 'local', 'test.csv', $admin->id, false);
        $finished = $this->manager()->run($run);

        $this->assertSame(ProductImportRun::STATUS_SUCCESS, $finished->status);
        $this->assertSame(2, $finished->created_count);
        $this->assertSame(1, $finished->skipped_count);
        $this->assertSame(1, $finished->error_count);
        $this->assertCount(1, $finished->errors);
        $this->assertSame(3, $finished->errors[0]['row']); // row 3 = 2nd data row (row 1 is the header)
        $this->assertStringContainsString("manufacturer slug 'does-not-exist' not found", $finished->errors[0]['message']);
    }

    #[Test]
    public function invalid_headers_fail_the_run_with_a_clear_message(): void
    {
        $admin = Admin::factory()->create();
        $path  = $this->putCsv('imports/test.csv', [
            ['oem_number', 'price'], // missing required columns
            ['OEM1', '10.00'],
        ]);

        $run      = $this->manager()->start($path, 'local', 'test.csv', $admin->id, false);
        $finished = $this->manager()->run($run);

        $this->assertSame(ProductImportRun::STATUS_FAILED, $finished->status);
        $this->assertStringContainsString('missing column', (string) $finished->error);
    }

    #[Test]
    public function completion_writes_a_bulk_update_log_row_and_invalidates_caches(): void
    {
        $this->seedCatalog();
        $admin = Admin::factory()->create();
        Cache::put('sitemap_parts', 'stale-value', now()->addHour());

        $path = $this->putCsv('imports/test.csv', [
            ['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock'],
            ['OEM1', 'bmw', 'new', '10.00', '1'],
        ]);

        $run = $this->manager()->start($path, 'local', 'test.csv', $admin->id, false);
        $this->manager()->run($run);

        $this->assertSame(1, BulkUpdateLog::where('action_type', BulkUpdateAction::Import->value)->count());
        $this->assertNull(Cache::get('sitemap_parts'));
    }
}
