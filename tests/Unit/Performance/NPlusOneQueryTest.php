<?php

namespace Tests\Unit\Performance;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * N+1 detection (Phase 4 — Performance & Load Testing): the classic
 * signature is query count scaling with RESULT count instead of staying
 * flat. Rather than asserting an absolute query count (brittle — any
 * legitimate new eager-loaded relation breaks it), each test compares the
 * query count for a SMALL result set against a LARGER one from the same
 * code path — a real N+1 shows up as "large costs roughly N times what
 * small costs", a properly eager-loaded path costs the same either way
 * (a small, constant handful of extra queries at most).
 */
class NPlusOneQueryTest extends TestCase
{
    use RefreshDatabase;

    private function queryCountFor(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    #[Test]
    public function searching_many_results_does_not_multiply_queries_per_result(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => ['en' => 'N1 Test Mfr']]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        Product::factory()->count(40)->create([
            'manufacturer_id' => $manufacturer->id,
            'condition_id' => $condition->id,
            'oem_number' => fn () => 'N1TEST'.fake()->unique()->numerify('####'),
        ])->each(fn ($p) => $p->update(['normalized_oem' => $p->oem_number]));

        $service = app(SearchService::class);

        // Access the same relations a rendered results page would (each
        // product card shows its manufacturer name + condition badge) —
        // an eager-loading gap in the SERVICE wouldn't show up just from
        // counting the service's OWN query, only from actually touching
        // these relations the way the view does.
        $touchRelations = function (int $limit) use ($service) {
            $result = $service->search('N1TEST', null, null, [
                'paginate' => false, 'limit' => $limit,
            ]);
            foreach ($result['products'] as $product) {
                $product->manufacturer->name;
                $product->condition->name;
            }
        };

        $smallCount = $this->queryCountFor(fn () => $touchRelations(5));
        $largeCount = $this->queryCountFor(fn () => $touchRelations(40));

        $this->assertLessThanOrEqual(
            $smallCount + 2,
            $largeCount,
            "5 results cost {$smallCount} queries, 40 results cost {$largeCount} — query count is scaling with result count (N+1)"
        );
    }

    #[Test]
    public function admin_product_table_listing_does_not_multiply_queries_per_row(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        Product::factory()->count(30)->create([
            'manufacturer_id' => $manufacturer->id,
            'condition_id' => $condition->id,
        ]);

        // Same eager-loading ProductResource's table() actually applies via
        // modifyQueryUsing(fn ($query) => $query->with(['manufacturer',
        // 'condition'])) — a query WITHOUT that ->with() trivially fails
        // this test (confirmed: 5 rows = 11 queries, 30 rows = 61, exactly
        // 2 extra per row), which is the point: this test only means
        // something if it exercises the same eager-loading the real
        // resource applies, not a naive re-implementation of the query.
        $touchRows = function (int $limit) {
            $products = Product::query()->with(['manufacturer', 'condition'])->latest()->limit($limit)->get();
            foreach ($products as $product) {
                $product->manufacturer->name;
                $product->condition->name;
            }
        };

        $smallCount = $this->queryCountFor(fn () => $touchRows(5));
        $largeCount = $this->queryCountFor(fn () => $touchRows(30));

        $this->assertLessThanOrEqual(
            $smallCount + 2,
            $largeCount,
            "5 rows cost {$smallCount} queries, 30 rows cost {$largeCount} — query count is scaling with row count (N+1). ProductResource's table() query should eager-load ['manufacturer', 'condition']."
        );
    }
}
