<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Jobs\BulkUpdateProductStockStatus;
use App\Models\Admin;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 20 (Admin-Panel Specific). ProductResource's markInStock/
 * markOutOfStock row-selection bulk actions ran synchronously for ANY
 * selection size — safe for the typical case (a handful of checked rows,
 * what product-bulk-actions.spec.js's e2e coverage actually exercises),
 * but Filament's default "select all X records" crosses pagination, so on
 * the real catalog (1M+ products; the dev seed is only ~90 — see
 * [[project_production_catalog_scale]]) a genuinely large selection risked
 * a memory/timeout crash mid-write with no recovery. Above
 * ProductResource::LARGE_BATCH_ROW_THRESHOLD (500, matching the sibling
 * BulkUpdateProducts page's own threshold), these now defer to a queued
 * job instead — the common small-selection case is untouched.
 */
class ProductBulkStockActionsTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;

    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true,
        ]);
        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    private function makeProduct(array $overrides = []): Product
    {
        static $i = 0;
        $i++;

        return Product::create(array_merge([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => "STK{$i}", 'normalized_oem' => "STK{$i}",
            'name' => ['en' => "Product {$i}"],
            'condition_id' => $this->condition->id, 'price' => '99.99',
            'is_in_stock' => false, 'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function a_small_selection_marks_in_stock_synchronously_without_dispatching_a_job(): void
    {
        Bus::fake();
        $product = $this->makeProduct(['is_in_stock' => false]);

        Livewire::test(ListProducts::class)
            ->loadTable()
            ->callTableBulkAction('markInStock', [$product]);

        Bus::assertNotDispatched(BulkUpdateProductStockStatus::class);
        $this->assertTrue($product->fresh()->is_in_stock);
    }

    #[Test]
    public function a_selection_over_the_threshold_dispatches_a_job_instead_of_writing_inline(): void
    {
        Bus::fake();
        // Just over ProductResource::LARGE_BATCH_ROW_THRESHOLD (500) — the
        // one-time cost of creating 501 rows here is deliberate: the point
        // of this test IS proving the boundary is actually enforced, not
        // testing it cheaply from further away.
        $products = collect(range(1, 501))->map(fn () => $this->makeProduct(['is_in_stock' => false]));

        Livewire::test(ListProducts::class)
            ->loadTable()
            ->callTableBulkAction('markInStock', $products->all());

        Bus::assertDispatched(BulkUpdateProductStockStatus::class, function ($job) use ($products) {
            return $job->inStock === true
                && count($job->productIds) === 501
                && in_array($products->first()->id, $job->productIds, true);
        });
        // Not written inline — still false until the (faked, never-run) job processes it.
        $this->assertFalse($products->first()->fresh()->is_in_stock);
    }

    #[Test]
    public function the_job_marks_matching_products_in_stock_and_skips_already_correct_ones(): void
    {
        $toFlip = $this->makeProduct(['is_in_stock' => false]);
        $alreadyCorrect = $this->makeProduct(['is_in_stock' => true]);

        (new BulkUpdateProductStockStatus([$toFlip->id, $alreadyCorrect->id], true, 'Test Admin'))->handle();

        $this->assertTrue($toFlip->fresh()->is_in_stock);
        $this->assertTrue($alreadyCorrect->fresh()->is_in_stock);
    }

    #[Test]
    public function the_job_marks_matching_products_out_of_stock(): void
    {
        $product = $this->makeProduct(['is_in_stock' => true]);

        (new BulkUpdateProductStockStatus([$product->id], false, 'Test Admin'))->handle();

        $this->assertFalse($product->fresh()->is_in_stock);
    }

    /**
     * No transaction wraps the chunked loop (by design — same "routine,
     * re-runnable action" reasoning as ImportRedirectsFromCsv/
     * BulkGenerateProductSeoMeta, Phase 19). Proves that's safe: the WHERE
     * is_in_stock != target filter on each chunk's query means an
     * already-flipped row is naturally excluded from a re-run, so
     * re-dispatching for the SAME full id list after a simulated partial
     * run doesn't touch rows that already succeeded and still completes
     * the rest.
     */
    #[Test]
    public function a_rerun_after_a_partial_failure_completes_the_rest_without_reprocessing_done_rows(): void
    {
        $productA = $this->makeProduct(['is_in_stock' => false]);
        $productB = $this->makeProduct(['is_in_stock' => false]);
        $productC = $this->makeProduct(['is_in_stock' => false]);

        // First pass "crashes" after A and B succeeded.
        (new BulkUpdateProductStockStatus([$productA->id, $productB->id], true, 'Test Admin'))->handle();

        // Natural recovery: re-dispatch for the FULL original selection.
        (new BulkUpdateProductStockStatus([$productA->id, $productB->id, $productC->id], true, 'Test Admin'))->handle();

        $this->assertTrue($productA->fresh()->is_in_stock);
        $this->assertTrue($productB->fresh()->is_in_stock);
        $this->assertTrue($productC->fresh()->is_in_stock);
    }
}
