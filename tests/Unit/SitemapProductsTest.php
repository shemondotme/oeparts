<?php

namespace Tests\Unit;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * generateProductsSitemap() used to: (1) filter on is_in_stock, which is an
 * optional storefront filter, not a visibility gate (the real search page
 * only requires is_active) — dropping reachable, indexable out-of-stock
 * pages from the sitemap; and (2) write one <loc> entry per Product ROW
 * rather than per distinct OEM — multiple products sharing a normalized_oem
 * (different sellers/conditions) produced byte-identical duplicate <loc>
 * entries, since the search-result URL is keyed by OEM alone.
 */
class SitemapProductsTest extends TestCase
{
    use RefreshDatabase;

    private string $outputFile;

    protected function tearDown(): void
    {
        if (isset($this->outputFile) && file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }

        parent::tearDown();
    }

    private function generateProductsSitemap(): string
    {
        $service = app(SitemapService::class);
        $method = new \ReflectionMethod($service, 'generateProductsSitemap');
        $method->setAccessible(true);
        $method->invoke($service);

        $this->outputFile = public_path('sitemaps/sitemap-parts.xml');

        return file_exists($this->outputFile) ? file_get_contents($this->outputFile) : '';
    }

    #[Test]
    public function out_of_stock_active_products_are_still_included(): void
    {
        $manufacturer = Manufacturer::create(['name' => ['en' => 'Mfr'], 'slug' => 'mfr', 'country_code' => 'DE', 'is_active' => true]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'OOS123', 'normalized_oem' => 'OOS123',
            'name' => ['en' => 'Out of stock part'], 'description' => ['en' => 'x'],
            'price' => 10, 'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => false,
        ]);

        $xml = $this->generateProductsSitemap();

        $this->assertStringContainsString('/parts/OOS123', $xml);
    }

    #[Test]
    public function duplicate_oems_across_multiple_product_rows_write_only_one_loc_entry(): void
    {
        $manufacturer = Manufacturer::create(['name' => ['en' => 'Mfr'], 'slug' => 'mfr', 'country_code' => 'DE', 'is_active' => true]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'DUP123', 'normalized_oem' => 'DUP123',
            'name' => ['en' => 'Seller A part'], 'description' => ['en' => 'x'],
            'price' => 10, 'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
        Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'DUP123', 'normalized_oem' => 'DUP123',
            'name' => ['en' => 'Seller B part'], 'description' => ['en' => 'x'],
            'price' => 12, 'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);

        $xml = $this->generateProductsSitemap();

        // One entry per locale is correct (5 supported locales); the bug
        // was writing one entry per DUPLICATE PRODUCT ROW on top of that —
        // count just the English URL, which must appear exactly once.
        $this->assertSame(1, substr_count($xml, '/en/parts/DUP123'));
    }
}
