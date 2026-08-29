<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Homepage entries and every sub-sitemap's <lastmod> in the top-level index
 * used to be hardcoded to now() on every single regeneration regardless of
 * whether the underlying content actually changed — the "always fresh"
 * pattern Google's own sitemap guidance says it will start discounting.
 * Both now reflect a real content date instead.
 */
class SitemapLastmodTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        array_map('unlink', glob(public_path('sitemaps/*.xml')) ?: []);
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
    }

    private function makeManufacturer(string $updatedAt): Manufacturer
    {
        $manufacturer = Manufacturer::create(['name' => ['en' => 'Mfr'], 'slug' => 'mfr-'.uniqid(), 'country_code' => 'DE', 'is_active' => true]);
        DB::table('manufacturers')->where('id', $manufacturer->id)->update(['updated_at' => $updatedAt]);

        return $manufacturer->fresh();
    }

    #[Test]
    public function homepage_lastmod_reflects_the_most_recently_changed_active_product_not_now(): void
    {
        $manufacturer = $this->makeManufacturer('2020-06-01 00:00:00');
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        $product = Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'HOME001', 'normalized_oem' => 'HOME001',
            'name' => ['en' => 'Part'], 'price' => 10, 'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
        DB::table('products')->where('id', $product->id)->update(['updated_at' => '2021-03-15 10:00:00']);

        Http::fake();
        app(SitemapService::class)->generateAll();

        $xml = file_get_contents(public_path('sitemaps/sitemap-pages.xml'));

        $this->assertStringContainsString('<lastmod>2021-03-15T10:00:00', $xml);
        $this->assertStringNotContainsString('<lastmod>'.now()->format('Y-m-d'), $xml);
    }

    #[Test]
    public function sitemap_index_uses_each_sub_sitemaps_real_max_lastmod_not_now(): void
    {
        $this->makeManufacturer('2019-01-01 00:00:00');

        Http::fake();
        app(SitemapService::class)->generateAll();

        $index = file_get_contents(public_path('sitemap.xml'));

        // The brands sub-sitemap's own <sitemap> entry in the index must
        // carry the manufacturer's real updated_at, not the moment
        // generateAll() happened to run.
        $this->assertMatchesRegularExpression(
            '#<loc>.*sitemap-brands\.xml</loc>\s*<lastmod>2019-01-01T00:00:00#',
            $index
        );
    }
}
