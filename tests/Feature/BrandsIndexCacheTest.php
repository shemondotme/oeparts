<?php

namespace Tests\Feature;

use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ManufacturerController::index() (the /brands directory page, linked from
 * the nav on every page of the site) used to run a fresh, uncached query on
 * every single visit — a locale-aware ORDER BY on a JSON_EXTRACT expression
 * (can't use an index) plus a LIKE-filtered paginate(). It's now backed by
 * CacheService::rememberAllActiveManufacturers(): one cached, locale-
 * independent fetch of every active manufacturer, with the locale-aware
 * sort/letter-filter/pagination done in PHP on that cached collection.
 */
class BrandsIndexCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_brands_page_is_backed_by_a_cached_query(): void
    {
        Manufacturer::create(['name' => ['en' => 'Alpha'], 'slug' => 'alpha', 'country_code' => 'DE', 'is_active' => true]);

        $this->get('/en/brands')->assertOk();

        $this->assertNotNull(Cache::get('manufacturers.active.all'));
    }

    #[Test]
    public function creating_a_manufacturer_invalidates_the_brands_page_cache(): void
    {
        $this->get('/en/brands');
        $this->assertNotNull(Cache::get('manufacturers.active.all'));

        Manufacturer::create(['name' => ['en' => 'Fresh Brand'], 'slug' => 'fresh-brand', 'country_code' => 'DE', 'is_active' => true]);

        $this->assertNull(Cache::get('manufacturers.active.all'), 'ManufacturerObserver must forget the stale listing');

        $this->get('/en/brands')->assertSee('Fresh Brand');
    }

    #[Test]
    public function deactivating_a_manufacturer_removes_it_from_the_brands_page(): void
    {
        $manufacturer = Manufacturer::create(['name' => ['en' => 'Gone Soon'], 'slug' => 'gone-soon', 'country_code' => 'DE', 'is_active' => true]);

        $this->get('/en/brands')->assertSee('Gone Soon');

        $manufacturer->update(['is_active' => false]);

        $this->get('/en/brands')->assertDontSee('Gone Soon');
    }

    #[Test]
    public function pagination_still_returns_the_correct_page_of_results(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['group' => 'general', 'key' => 'pagination_per_page'],
            ['value' => '2', 'type' => 'integer', 'is_encrypted' => false]
        );
        app(\App\Services\SettingsService::class)->forget('general');

        Manufacturer::create(['name' => ['en' => 'Aaa'], 'slug' => 'aaa', 'country_code' => 'DE', 'is_active' => true]);
        Manufacturer::create(['name' => ['en' => 'Bbb'], 'slug' => 'bbb', 'country_code' => 'DE', 'is_active' => true]);
        Manufacturer::create(['name' => ['en' => 'Ccc'], 'slug' => 'ccc', 'country_code' => 'DE', 'is_active' => true]);

        $page1 = $this->get('/en/brands?page=1');
        $page1->assertSee('Aaa')->assertSee('Bbb')->assertDontSee('Ccc');

        $page2 = $this->get('/en/brands?page=2');
        $page2->assertSee('Ccc')->assertDontSee('Aaa')->assertDontSee('Bbb');
    }
}
