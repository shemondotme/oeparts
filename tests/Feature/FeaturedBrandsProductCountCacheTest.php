<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The featured-brands homepage tile's "N pcs" count was a raw, uncached
 * GROUP BY COUNT query embedded directly in the Blade component — run fresh
 * on every single homepage render even though the section's own manufacturer
 * list is already cached. A user-applied fix (Cache::remember with a flat
 * 7-day TTL and no invalidation) fixed the speed but introduced a
 * correctness gap: the displayed count would silently drift for up to a
 * week after any product write. CacheService::rememberBrandProductCounts()
 * gets the same speed win properly — same TTL/on-off knob as every other
 * homepage cache in this app, and keyed by SearchService::cacheVersion()
 * (already bumped by ProductObserver on every product create/update/delete),
 * so a write invalidates it immediately instead of waiting out a fixed TTL.
 */
class FeaturedBrandsProductCountCacheTest extends TestCase
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

    private function product(): Product
    {
        static $n = 0;
        $n++;

        return Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => "OEM{$n}", 'normalized_oem' => "OEM{$n}",
            'name' => ['en' => 'x'], 'description' => ['en' => 'x'], 'price' => 10,
            'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
    }

    #[Test]
    public function it_counts_active_products_per_manufacturer(): void
    {
        $this->product();
        $this->product();

        $counts = app(CacheService::class)->rememberBrandProductCounts(
            [$this->manufacturer->id],
            fn () => Product::where('manufacturer_id', $this->manufacturer->id)
                ->where('is_active', true)
                ->groupBy('manufacturer_id')
                ->selectRaw('manufacturer_id, COUNT(*) as count')
                ->pluck('count', 'manufacturer_id')
                ->toArray()
        );

        $this->assertSame(2, $counts[$this->manufacturer->id]);
    }

    #[Test]
    public function a_cached_count_does_not_reflect_a_product_added_without_invalidation(): void
    {
        // Establishes the cache is genuinely a cache (not accidentally a
        // pass-through) before proving the real invalidation path below.
        $this->product();

        $callback = fn () => Product::where('manufacturer_id', $this->manufacturer->id)
            ->where('is_active', true)->count();

        $first = app(CacheService::class)->rememberBrandProductCounts([$this->manufacturer->id], $callback);

        \Illuminate\Support\Facades\DB::table('products')->insert([
            'manufacturer_id' => $this->manufacturer->id, 'oem_number' => 'DIRECT', 'normalized_oem' => 'DIRECT',
            'name' => json_encode(['en' => 'x']), 'description' => json_encode(['en' => 'x']), 'price' => 10,
            'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $stillCached = app(CacheService::class)->rememberBrandProductCounts([$this->manufacturer->id], $callback);

        $this->assertSame($first, $stillCached, 'a raw DB write (no Eloquent event) does not bust the cache — expected');
    }

    #[Test]
    public function saving_a_product_through_eloquent_invalidates_the_cached_count(): void
    {
        $this->product();

        $callback = fn () => Product::where('manufacturer_id', $this->manufacturer->id)
            ->where('is_active', true)->count();

        $before = app(CacheService::class)->rememberBrandProductCounts([$this->manufacturer->id], $callback);
        $this->assertSame(1, $before);

        // ProductObserver::invalidateCache() bumps SearchService::cacheVersion()
        // unconditionally on every create/update/delete.
        $this->product();

        $after = app(CacheService::class)->rememberBrandProductCounts([$this->manufacturer->id], $callback);
        $this->assertSame(2, $after, 'the new product is reflected immediately, not after a stale TTL');
    }

    #[Test]
    public function the_cache_toggle_setting_bypasses_caching_entirely(): void
    {
        \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
            ['group' => 'performance', 'key' => 'cache_manufacturers'],
            ['value' => 'false', 'type' => 'boolean', 'is_encrypted' => false]
        );
        app(\App\Services\SettingsService::class)->forget('performance');

        $this->product();
        $callback = fn () => Product::where('manufacturer_id', $this->manufacturer->id)
            ->where('is_active', true)->count();

        $before = app(CacheService::class)->rememberBrandProductCounts([$this->manufacturer->id], $callback);
        $this->product();
        $after = app(CacheService::class)->rememberBrandProductCounts([$this->manufacturer->id], $callback);

        $this->assertSame(1, $before);
        $this->assertSame(2, $after, 'with caching off, every call re-runs the callback live');
    }
}
