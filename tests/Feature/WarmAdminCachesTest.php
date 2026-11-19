<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoHealthDashboard;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The catalogue-wide figures of SEO Health and the Manufacturers list are counted in the background, so that opening
 * those pages after an update or a cache clear never waits for the counting.
 */
class WarmAdminCachesTest extends TestCase
{
    use RefreshDatabase;

    private function part(Manufacturer $m, string $oem): Product
    {
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        return Product::create([
            'manufacturer_id' => $m->id, 'oem_number' => $oem, 'normalized_oem' => $oem, 'name' => ['en' => 'Part'],
            'price' => 10, 'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
    }

    #[Test]
    public function the_warm_up_fills_every_cache_the_two_pages_read(): void
    {
        $m = Manufacturer::create(['name' => ['en' => 'BMW'], 'slug' => 'bmw', 'country_code' => 'DE', 'is_active' => true]);
        $this->part($m, 'A1');
        $this->part($m, 'A2');
        Cache::flush();

        $this->artisan('oeparts:cache:warm-admin')->assertSuccessful();

        foreach (['admin:seo-health:content', 'admin:seo-health:onpage', 'admin:seo-health:adoption', 'admin:manufacturers:product-counts'] as $key) {
            $this->assertNotNull(Cache::get($key), "{$key} is filled");
            $this->assertNotNull(Cache::get("illuminate:cache:flexible:created:{$key}"), "{$key} has its creation time");
        }
        $this->assertSame([$m->id => 2], Cache::get('admin:manufacturers:product-counts'));
    }

    #[Test]
    public function the_pages_then_read_the_warmed_figures_without_counting_again(): void
    {
        $m = Manufacturer::create(['name' => ['en' => 'BMW'], 'slug' => 'bmw', 'country_code' => 'DE', 'is_active' => true]);
        $this->part($m, 'A1');
        Cache::flush();
        $this->artisan('oeparts:cache:warm-admin')->assertSuccessful();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $page = app(SeoHealthDashboard::class);
        $page->contentHealth();
        $page->onPageAudit();
        $page->featureAdoption();
        Manufacturer::productCounts();
        $productQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "products"') || str_contains($q['query'], 'from `products`'))->count();

        $this->assertSame(0, $productQueries, 'no catalogue count runs when the page is opened');
    }

    #[Test]
    public function the_warm_up_is_on_the_schedule(): void
    {
        $this->assertTrue(collect(app(Schedule::class)->events())->contains(fn ($e) => str_contains((string) $e->command, 'oeparts:cache:warm-admin')));
    }

    #[Test]
    public function clearing_the_cache_leaves_one_request_that_the_scheduler_picks_up_within_a_minute(): void
    {
        app(CacheService::class)->requestAdminWarmUp();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'oeparts:cache:warm-admin') && $e->expression === '* * * * *');

        $this->assertNotNull($event, 'there is a once-a-minute entry');
        $this->assertTrue($event->filtersPass($this->app), 'it runs when a request is waiting');
        $this->assertFalse($event->filtersPass($this->app), 'and the request is used up by that one run');
    }
}
