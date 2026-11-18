<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A big brand (Mercedes-Benz has a million parts) used to run a COUNT(*) over every part plus an
 * ORDER BY on each visit, so its page took many seconds to start. The total and the page's rows are
 * now cached together and refreshed whenever a product changes.
 */
class BrandPageCacheTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $brand;

    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $this->brand = Manufacturer::create(['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);
        \App\Models\Setting::updateOrCreate(['group' => 'general', 'key' => 'pagination_per_page'], ['value' => '2', 'type' => 'integer', 'is_encrypted' => false]);
        app(\App\Services\SettingsService::class)->forget('general');
    }

    private function part(string $oem, bool $active = true): Product
    {
        return Product::create([
            'manufacturer_id' => $this->brand->id, 'oem_number' => $oem, 'normalized_oem' => $oem,
            'name' => ['en' => 'Part '.$oem], 'price' => 10, 'condition_id' => $this->condition->id, 'is_active' => $active, 'is_in_stock' => true,
        ]);
    }

    /** @return int how many queries a request ran against the products table */
    private function productQueriesDuring(callable $request): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $request();
        $n = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from `products`') || str_contains($q['query'], 'from "products"'))->count();
        DB::disableQueryLog();

        return $n;
    }

    #[Test]
    public function the_brand_page_still_lists_and_paginates_its_active_parts(): void
    {
        foreach (['PN1001', 'PN1002', 'PN1003', 'PN1004', 'PN1005'] as $oem) {
            $this->part($oem);
        }
        $this->part('PNHIDDEN', false);

        $first = $this->get('/en/brand/acme')->assertOk();
        $first->assertSee('PN1001')->assertSee('PN1002')->assertDontSee('PN1003')->assertDontSee('PNHIDDEN');
        $first->assertSee('page=2', false);

        $this->get('/en/brand/acme?page=3')->assertOk()->assertSee('PN1005')->assertDontSee('PN1001');
        $this->get('/en/brand/acme?page=4')->assertNotFound();
    }

    #[Test]
    public function a_second_visit_reads_the_total_and_rows_from_the_cache(): void
    {
        $this->part('PN1001');
        $this->part('PN1002');

        $cold = $this->productQueriesDuring(fn () => $this->get('/en/brand/acme')->assertOk());
        $warm = $this->productQueriesDuring(fn () => $this->get('/en/brand/acme')->assertOk()->assertSee('PN1001'));

        $this->assertGreaterThan(0, $cold);
        $this->assertSame(0, $warm, 'the products table must not be queried again while the cache is warm');
    }

    #[Test]
    public function changing_a_product_refreshes_the_cached_page(): void
    {
        $this->part('PN1001');
        $this->get('/en/brand/acme')->assertOk()->assertSee('PN1001')->assertDontSee('PN9009');

        $this->part('PN9009');

        $this->get('/en/brand/acme')->assertOk()->assertSee('PN9009');
    }
}
