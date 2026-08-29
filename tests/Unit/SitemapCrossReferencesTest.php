<?php

namespace Tests\Unit;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Models\Setting;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-reference OEM numbers are functionally searchable
 * (SearchService::crossReferenceMatch()) but were never proactively listed
 * in the sitemap — Google only found them by crawling internal links, with
 * no priority signal. This is the fix: generateCrossReferencesSitemap(),
 * deduped GLOBALLY by normalized_cross_oem (not per-product).
 */
class SitemapCrossReferencesTest extends TestCase
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

    private function generateCrossReferencesSitemap(): string
    {
        $service = app(SitemapService::class);
        $method = new \ReflectionMethod($service, 'generateCrossReferencesSitemap');
        $method->setAccessible(true);
        $method->invoke($service);

        $this->outputFile = public_path('sitemaps/sitemap-crossrefs.xml');

        return file_exists($this->outputFile) ? file_get_contents($this->outputFile) : '';
    }

    private function makeProduct(array $overrides = []): Product
    {
        $manufacturer = Manufacturer::create(['name' => ['en' => 'Mfr'], 'slug' => 'mfr-' . uniqid(), 'country_code' => 'DE', 'is_active' => true]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        return Product::create(array_merge([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => 'PRIM' . uniqid(),
            'normalized_oem' => 'PRIM' . uniqid(),
            'name' => ['en' => 'Part'],
            'price' => 10,
            'condition_id' => $condition->id,
            'is_active' => true,
            'is_in_stock' => true,
        ], $overrides));
    }

    #[Test]
    public function a_cross_ref_with_a_single_active_product_and_detail_pages_enabled_points_to_the_detail_url(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'detail_pages_enabled'], ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]);

        $product = $this->makeProduct(['normalized_oem' => 'PRIM001']);
        $product->crossReferences()->create(['cross_oem_number' => 'XREF001', 'normalized_cross_oem' => 'XREF001']);

        $xml = $this->generateCrossReferencesSitemap();

        $this->assertStringContainsString("/en/parts/PRIM001/{$product->id}-", $xml);
        $this->assertStringNotContainsString('/en/parts/XREF001', $xml);
    }

    #[Test]
    public function a_cross_ref_with_detail_pages_disabled_points_to_the_hub_url(): void
    {
        // Default (off).
        $product = $this->makeProduct(['normalized_oem' => 'PRIM002']);
        $product->crossReferences()->create(['cross_oem_number' => 'XREF002', 'normalized_cross_oem' => 'XREF002']);

        $xml = $this->generateCrossReferencesSitemap();

        $this->assertStringContainsString('/en/parts/XREF002', $xml);
    }

    #[Test]
    public function a_cross_ref_shared_by_multiple_active_products_points_to_the_hub_url_even_with_detail_pages_enabled(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'detail_pages_enabled'], ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]);

        $productA = $this->makeProduct(['normalized_oem' => 'PRIM003']);
        $productA->crossReferences()->create(['cross_oem_number' => 'XREF003', 'normalized_cross_oem' => 'XREF003']);
        $productB = $this->makeProduct(['normalized_oem' => 'PRIM004']);
        $productB->crossReferences()->create(['cross_oem_number' => 'XREF003-ALT', 'normalized_cross_oem' => 'XREF003']);

        $xml = $this->generateCrossReferencesSitemap();

        $this->assertStringContainsString('/en/parts/XREF003', $xml);
        $this->assertSame(1, substr_count($xml, '<loc>' . url('/en/parts/XREF003') . '</loc>'));
    }

    #[Test]
    public function global_dedupe_writes_only_one_entry_per_distinct_cross_oem_regardless_of_row_count(): void
    {
        $product = $this->makeProduct(['normalized_oem' => 'PRIM005']);
        // Two rows, same normalized_cross_oem (e.g. differently-formatted
        // input that normalized to the same value).
        ProductCrossReference::create(['product_id' => $product->id, 'cross_oem_number' => 'XREF-005', 'normalized_cross_oem' => 'XREF005']);
        ProductCrossReference::create(['product_id' => $product->id, 'cross_oem_number' => 'XREF 005', 'normalized_cross_oem' => 'XREF005']);

        $xml = $this->generateCrossReferencesSitemap();

        $this->assertSame(1, substr_count($xml, '<loc>' . url('/en/parts/XREF005') . '</loc>'));
    }

    #[Test]
    public function a_product_with_multiple_distinct_cross_oems_gets_one_detail_url_entry_per_locale_not_one_per_cross_oem(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'detail_pages_enabled'], ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]);

        $product = $this->makeProduct(['normalized_oem' => 'PRIM008']);
        $product->crossReferences()->create(['cross_oem_number' => 'XREF008A', 'normalized_cross_oem' => 'XREF008A']);
        $product->crossReferences()->create(['cross_oem_number' => 'XREF008B', 'normalized_cross_oem' => 'XREF008B']);

        $xml = $this->generateCrossReferencesSitemap();

        $this->assertSame(1, substr_count($xml, "/en/parts/PRIM008/{$product->id}-"));
    }

    #[Test]
    public function cross_ref_belonging_only_to_an_inactive_product_is_excluded(): void
    {
        $product = $this->makeProduct(['normalized_oem' => 'PRIM006', 'is_active' => false]);
        $product->crossReferences()->create(['cross_oem_number' => 'XREF006', 'normalized_cross_oem' => 'XREF006']);

        $xml = $this->generateCrossReferencesSitemap();

        $this->assertStringNotContainsString('XREF006', $xml);
    }

    #[Test]
    public function entries_use_priority_0_5_and_weekly_changefreq(): void
    {
        $product = $this->makeProduct(['normalized_oem' => 'PRIM007']);
        $product->crossReferences()->create(['cross_oem_number' => 'XREF007', 'normalized_cross_oem' => 'XREF007']);

        $xml = $this->generateCrossReferencesSitemap();

        $this->assertStringContainsString('<priority>0.5</priority>', $xml);
        $this->assertStringContainsString('<changefreq>weekly</changefreq>', $xml);
    }
}
