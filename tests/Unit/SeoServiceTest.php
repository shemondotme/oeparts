<?php

namespace Tests\Unit;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\SeoMeta;
use App\Models\Setting;
use App\Services\SeoService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeoServiceTest extends TestCase
{
    use RefreshDatabase;

    private SeoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
        $this->service = app(SeoService::class);
    }

    private function seedSettings(): void
    {
        Setting::updateOrCreate(
            ['group' => 'general', 'key' => 'site_name'],
            ['value' => 'OeParts', 'type' => 'string', 'is_encrypted' => false]
        );
        Setting::updateOrCreate(
            ['group' => 'general', 'key' => 'currency'],
            ['value' => 'EUR', 'type' => 'string', 'is_encrypted' => false]
        );
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'default_description'],
            ['value' => 'Default SEO description', 'type' => 'string', 'is_encrypted' => false]
        );
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'default_robots'],
            ['value' => 'index,follow', 'type' => 'string', 'is_encrypted' => false]
        );

        app(SettingsService::class)->forget('general');
        app(SettingsService::class)->forget('seo');
    }

    private function createManufacturer(): Manufacturer
    {
        return Manufacturer::create([
            'name' => '{"en":"Bosch"}',
            'slug' => 'bosch',
            'country_code' => 'DE',
            'is_active' => true,
        ]);
    }

    // ─── jsonLd() ───────────────────────────────────────────────────────────

    #[Test]
    public function json_ld_website_returns_website_schema(): void
    {
        $output = $this->service->jsonLd('website');

        $this->assertStringContainsString('"@type":"WebSite"', $output);
        $this->assertStringContainsString('"name":"OeParts"', $output);
        $this->assertStringContainsString('SearchAction', $output);
    }

    #[Test]
    public function json_ld_product_returns_product_schema(): void
    {
        $manufacturer = $this->createManufacturer();
        $product = Product::factory()->create([
            'manufacturer_id' => $manufacturer->id,
            'price' => '140.00',
            'is_in_stock' => true,
        ]);

        $output = $this->service->jsonLd('product', $product);

        $this->assertStringContainsString('"@type":"Product"', $output);
        $this->assertStringContainsString('"sku":"'.$product->oem_number.'"', $output);
        $this->assertStringContainsString('"price":"140.00"', $output);
        $this->assertStringContainsString('"priceCurrency":"EUR"', $output);
        $this->assertStringContainsString('InStock', $output);
    }

    #[Test]
    public function json_ld_product_additional_property_includes_primary_and_cross_reference_oems(): void
    {
        $manufacturer = $this->createManufacturer();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);
        $product->crossReferences()->create([
            'cross_oem_number' => 'XREF999',
            'normalized_cross_oem' => 'XREF999',
        ]);

        $output = $this->service->jsonLd('product', $product->fresh(['crossReferences']));

        $this->assertStringContainsString('"additionalProperty"', $output);
        $this->assertStringContainsString('"value":"' . $product->oem_number . '"', $output);
        $this->assertStringContainsString('"value":"XREF999"', $output);
        $this->assertStringContainsString('"name":"OEM Number"', $output);
    }

    #[Test]
    public function json_ld_product_additional_property_has_no_duplicates_when_lists_overlap(): void
    {
        $manufacturer = $this->createManufacturer();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);
        // Data-quality edge case: a cross-reference row accidentally equal
        // to the product's own primary OEM must not produce a duplicate
        // PropertyValue entry.
        $product->crossReferences()->create([
            'cross_oem_number' => $product->oem_number,
            'normalized_cross_oem' => $product->normalized_oem,
        ]);

        $output = $this->service->jsonLd('product', $product->fresh(['crossReferences']));

        $this->assertSame(1, substr_count($output, '"value":"' . $product->oem_number . '"'));
    }

    #[Test]
    public function json_ld_product_sku_and_mpn_both_equal_the_primary_oem_number(): void
    {
        // Locking assertion — a future refactor touching productJsonLd()
        // must not silently regress this without a test failing.
        $manufacturer = $this->createManufacturer();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);

        $output = $this->service->jsonLd('product', $product);

        $this->assertStringContainsString('"sku":"' . $product->oem_number . '"', $output);
        $this->assertStringContainsString('"mpn":"' . $product->oem_number . '"', $output);
    }

    #[Test]
    public function json_ld_product_out_of_stock_reflects_availability(): void
    {
        $manufacturer = $this->createManufacturer();
        $product = Product::factory()->outOfStock()->create(['manufacturer_id' => $manufacturer->id]);

        $output = $this->service->jsonLd('product', $product);

        $this->assertStringContainsString('OutOfStock', $output);
    }

    #[Test]
    public function json_ld_breadcrumb_returns_breadcrumb_list_schema_with_home_prepended(): void
    {
        $output = $this->service->jsonLd('breadcrumb', [
            ['label' => 'Bosch', 'url' => 'https://oeparts.test/en/brand/bosch'],
            ['label' => '06L906036L', 'url' => 'https://oeparts.test/en/parts/06L906036L'],
        ]);

        $this->assertStringContainsString('"@type":"BreadcrumbList"', $output);
        $this->assertStringContainsString('"position":1', $output);
        $this->assertStringContainsString('"name":"OeParts"', $output); // auto-prepended Home entry
        $this->assertStringContainsString('"position":2', $output);
        $this->assertStringContainsString('"name":"Bosch"', $output);
        $this->assertStringContainsString('"position":3', $output);
        $this->assertStringContainsString('"name":"06L906036L"', $output);
    }

    #[Test]
    public function json_ld_breadcrumb_with_empty_array_still_returns_home_only(): void
    {
        $output = $this->service->jsonLd('breadcrumb', []);

        $this->assertStringContainsString('"@type":"BreadcrumbList"', $output);
        $this->assertStringContainsString('"position":1', $output);
    }

    #[Test]
    public function json_ld_article_returns_article_schema(): void
    {
        $admin = Admin::factory()->create();
        $category = Category::create(['name' => '{"en":"News"}', 'slug' => 'news']);
        $post = BlogPost::create([
            'category_id' => $category->id,
            'author_id' => $admin->id,
            'title' => '{"en":"Test Article"}',
            'slug' => 'test-article',
            'content' => '{"en":"Content"}',
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);

        $output = $this->service->jsonLd('article', $post);

        $this->assertStringContainsString('"@type":"Article"', $output);
        $this->assertStringContainsString('"headline"', $output);
    }

    #[Test]
    public function json_ld_organization_returns_organization_schema(): void
    {
        $output = $this->service->jsonLd('organization');

        $this->assertStringContainsString('"@type":"Organization"', $output);
        $this->assertStringContainsString('"name":"OeParts"', $output);
    }

    #[Test]
    public function json_ld_with_null_entity_returns_empty(): void
    {
        $this->assertSame('', $this->service->jsonLd('product', null));
        $this->assertSame('', $this->service->jsonLd('article', null));
    }

    // ─── getMetaFor() ───────────────────────────────────────────────────────

    #[Test]
    public function get_meta_for_entity_with_seo_meta_returns_meta(): void
    {
        $manufacturer = $this->createManufacturer();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);
        SeoMeta::create([
            'metable_type' => Product::class,
            'metable_id' => $product->id,
            'meta_title' => 'Custom Title',
            'meta_description' => 'Custom Description',
            'canonical_url' => 'https://example.com/custom',
            'robots' => 'noindex',
        ]);

        $meta = $this->service->getMetaFor($product);

        $this->assertSame('Custom Title', $meta['meta_title']);
        $this->assertSame('Custom Description', $meta['meta_description']);
        $this->assertSame('https://example.com/custom', $meta['canonical_url']);
        $this->assertSame('noindex', $meta['robots']);
    }

    #[Test]
    public function get_meta_for_entity_without_seo_meta_returns_defaults(): void
    {
        $manufacturer = $this->createManufacturer();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);

        $meta = $this->service->getMetaFor($product);

        $this->assertNull($meta['meta_title']);
        $this->assertSame('Default SEO description', $meta['meta_description']);
        $this->assertSame('index,follow', $meta['robots']);
    }

    #[Test]
    public function get_meta_for_null_returns_defaults(): void
    {
        $meta = $this->service->getMetaFor(null);

        $this->assertNull($meta['meta_title']);
        $this->assertSame('Default SEO description', $meta['meta_description']);
        $this->assertSame('index,follow', $meta['robots']);
    }

    // ─── canonicalUrl() ─────────────────────────────────────────────────────

    #[Test]
    public function canonical_url_with_entity_override_returns_override(): void
    {
        $manufacturer = $this->createManufacturer();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);
        SeoMeta::create([
            'metable_type' => Product::class,
            'metable_id' => $product->id,
            'canonical_url' => 'https://example.com/override',
        ]);

        $url = $this->service->canonicalUrl($product);

        $this->assertSame('https://example.com/override', $url);
    }

    // ─── ogImageTag() ───────────────────────────────────────────────────────

    #[Test]
    public function og_image_tag_with_image_id_returns_tag(): void
    {
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'default_og_image'],
            ['value' => 'images/og-default.png', 'type' => 'string', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('seo');

        $tag = $this->service->ogImageTag(999);

        $this->assertStringContainsString('og:image', $tag);
        $this->assertStringContainsString('images/og-default.png', $tag);
    }

    /**
     * ogImageTag() used to ignore $ogImageId entirely — both branches
     * returned the same site-wide default, so a per-entity OG image
     * selected in SeoMeta's Advanced SEO section never actually appeared.
     */
    #[Test]
    public function og_image_tag_with_a_real_image_id_uses_that_images_url_not_the_default(): void
    {
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'default_og_image'],
            ['value' => 'images/og-default.png', 'type' => 'string', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('seo');

        $admin = Admin::factory()->create();
        $image = \App\Models\MediaFile::create([
            'uploaded_by' => $admin->id,
            'file_name' => 'custom-og.png',
            'file_path' => 'media/custom-og.png',
            'file_url' => 'ignored-legacy-value',
            'mime_type' => 'image/png',
            'size' => 12345,
        ]);

        $tag = $this->service->ogImageTag($image->id);

        $this->assertStringContainsString('media/custom-og.png', $tag);
        $this->assertStringNotContainsString('images/og-default.png', $tag);
    }

    #[Test]
    public function og_image_tag_without_image_id_but_default_returns_default(): void
    {
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'default_og_image'],
            ['value' => 'images/og-default.png', 'type' => 'string', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('seo');

        $tag = $this->service->ogImageTag(null);

        $this->assertStringContainsString('og:image', $tag);
        $this->assertStringContainsString('images/og-default.png', $tag);
    }

    #[Test]
    public function og_image_tag_with_no_config_returns_empty(): void
    {
        $tag = $this->service->ogImageTag(null);

        $this->assertSame('', $tag);
    }
}
