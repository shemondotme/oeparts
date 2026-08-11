<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Search Console landing page's (/{lang}/parts) status-panel counts
 * ("N brands", "N products") were cached with a bare Cache::remember() and
 * NO matching forget() call anywhere in the codebase — same bug class as
 * the featured-brands product-count fix. An admin deactivating a product or
 * manufacturer left this panel showing a wrong count for up to
 * search.cache_ttl_hours (default 6h), with no manual "Clear" path either.
 */
class SearchConsoleStatsCacheTest extends TestCase
{
    use RefreshDatabase;

    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]
        );
    }

    #[Test]
    public function the_console_page_shows_the_current_active_product_count(): void
    {
        $manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true,
        ]);
        Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'A1', 'normalized_oem' => 'A1',
            'name' => ['en' => 'x'], 'description' => ['en' => 'x'], 'price' => 10,
            'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);

        $this->get('/en/parts')->assertSeeText('1');
    }

    #[Test]
    public function creating_a_product_invalidates_the_stale_console_stats_cache(): void
    {
        $manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true,
        ]);

        // Prime the cache at 0 products, mirroring a real first page load.
        $this->get('/en/parts');
        $this->assertNotNull(Cache::get('search_console_stats'));

        Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'A1', 'normalized_oem' => 'A1',
            'name' => ['en' => 'x'], 'description' => ['en' => 'x'], 'price' => 10,
            'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);

        $this->assertNull(Cache::get('search_console_stats'), 'ProductObserver must forget the stale count');
    }

    #[Test]
    public function activating_a_manufacturer_invalidates_the_stale_console_stats_cache(): void
    {
        $manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => false,
        ]);

        $this->get('/en/parts');
        $this->assertNotNull(Cache::get('search_console_stats'));

        $manufacturer->update(['is_active' => true]);

        $this->assertNull(Cache::get('search_console_stats'), 'ManufacturerObserver must forget the stale count');
    }
}
