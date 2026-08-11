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
 * CacheService::forgetHeroStats()/forgetPopularOems() were only ever called
 * after a bulk CSV import (ProductImportService) or a manual "Clear" click
 * on the admin Cache Dashboard — see ProductImportCacheInvalidationTest for
 * that path. A single product created/edited/deleted through the normal
 * admin ProductResource form left the homepage hero "parts indexed" stat
 * stale for up to 6 hours (1h for the popular-OEMs strip), with no
 * immediate way to see the correct count. ProductObserver now forgets both
 * on every single product write too.
 */
class ProductWriteHeroStatsCacheTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;
    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true,
        ]);
        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]
        );
    }

    #[Test]
    public function creating_a_single_product_invalidates_hero_stats_and_popular_oems(): void
    {
        Cache::put('hero.stats', 'stale-value', now()->addHours(6));
        Cache::put('hero.popular_oems', 'stale-value', now()->addHour());

        Product::create([
            'manufacturer_id' => $this->manufacturer->id, 'oem_number' => 'A1', 'normalized_oem' => 'A1',
            'name' => ['en' => 'x'], 'description' => ['en' => 'x'], 'price' => 10,
            'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);

        $this->assertNull(Cache::get('hero.stats'));
        $this->assertNull(Cache::get('hero.popular_oems'));
    }

    #[Test]
    public function updating_a_product_invalidates_hero_stats_and_popular_oems(): void
    {
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id, 'oem_number' => 'A1', 'normalized_oem' => 'A1',
            'name' => ['en' => 'x'], 'description' => ['en' => 'x'], 'price' => 10,
            'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);

        Cache::put('hero.stats', 'stale-value', now()->addHours(6));
        Cache::put('hero.popular_oems', 'stale-value', now()->addHour());

        $product->update(['is_active' => false]);

        $this->assertNull(Cache::get('hero.stats'));
        $this->assertNull(Cache::get('hero.popular_oems'));
    }

    #[Test]
    public function deleting_a_product_invalidates_hero_stats_and_popular_oems(): void
    {
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id, 'oem_number' => 'A1', 'normalized_oem' => 'A1',
            'name' => ['en' => 'x'], 'description' => ['en' => 'x'], 'price' => 10,
            'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);

        Cache::put('hero.stats', 'stale-value', now()->addHours(6));
        Cache::put('hero.popular_oems', 'stale-value', now()->addHour());

        $product->delete();

        $this->assertNull(Cache::get('hero.stats'));
        $this->assertNull(Cache::get('hero.popular_oems'));
    }
}
