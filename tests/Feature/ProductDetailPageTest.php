<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ProductSlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;
    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch', 'de' => 'Bosch', 'lt' => 'Bosch', 'fr' => 'Bosch', 'es' => 'Bosch'],
            'slug' => 'bosch',
            'country_code' => 'DE',
            'is_active' => true,
        ]);
    }

    private function enableDetailPages(): void
    {
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'detail_pages_enabled'],
            ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]
        );
        // A raw Eloquent write, unlike SettingsService::set(), never busts
        // SettingsService::getGroup()'s 5-minute per-group cache.
        app(\App\Services\SettingsService::class)->forget('seo');
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Brake Pad Front'],
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function detailUrl(Product $product, string $lang = 'en'): string
    {
        $idSlug = app(ProductSlugService::class)->buildIdSlug($product, $lang);

        return "/{$lang}/parts/{$product->normalized_oem}/{$idSlug}";
    }

    #[Test]
    public function hub_only_mode_redirects_detail_url_to_hub_regardless_of_product_existing(): void
    {
        // Toggle left at its default (off).
        $product = $this->makeProduct();

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(301);
        $response->assertRedirect("/en/parts/{$product->normalized_oem}");
    }

    #[Test]
    public function hub_only_mode_redirects_even_for_a_nonexistent_product_id(): void
    {
        $response = $this->get('/en/parts/06L906036L/999999-nonexistent');

        $response->assertStatus(301);
        $response->assertRedirect('/en/parts/06L906036L');
    }

    #[Test]
    public function hub_and_detail_mode_renders_the_detail_page_for_an_active_product(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSeeText('06L906036L');
        $response->assertSeeText('Bosch');
        $response->assertSee('"@type":"Product"', false);
    }

    #[Test]
    public function detail_page_includes_cross_reference_panel_when_present(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();
        $product->crossReferences()->create([
            'cross_oem_number' => 'XREF999',
            'normalized_cross_oem' => 'XREF999',
        ]);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSeeText('XREF999');
    }

    #[Test]
    public function discontinued_product_redirects_to_hub(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct(['is_active' => false]);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(301);
        $response->assertRedirect("/en/parts/{$product->normalized_oem}");
    }

    #[Test]
    public function soft_deleted_product_redirects_to_hub(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();
        $url = $this->detailUrl($product);
        $product->delete();

        $response = $this->get($url);

        $response->assertStatus(301);
        $response->assertRedirect("/en/parts/{$product->normalized_oem}");
    }

    #[Test]
    public function single_exact_match_on_hub_auto_redirects_to_detail_page(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();

        $response = $this->get("/en/parts/{$product->normalized_oem}");

        $response->assertStatus(301);
        $response->assertRedirect($this->detailUrl($product));
    }

    #[Test]
    public function single_cross_reference_match_on_hub_auto_redirects_to_detail_page(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();
        $product->crossReferences()->create([
            'cross_oem_number' => 'XREF999',
            'normalized_cross_oem' => 'XREF999',
        ]);

        $response = $this->get('/en/parts/XREF999');

        $response->assertStatus(301);
        $response->assertRedirect($this->detailUrl($product));
    }

    #[Test]
    public function single_partial_match_stays_on_the_hub_page(): void
    {
        // A partial match is a substring hit, not a confirmed equality —
        // silently redirecting it away from its "partial match" context
        // would be a real trust/UX regression (judgment call from the plan).
        $this->enableDetailPages();
        $this->makeProduct();

        $response = $this->get('/en/parts/906036');

        $response->assertStatus(200);
        $response->assertSeeText('06L906036L');
    }

    #[Test]
    public function hub_page_still_shows_normally_when_multiple_products_match(): void
    {
        $this->enableDetailPages();
        $this->makeProduct();
        $this->makeProduct(['oem_number' => '06L906036L', 'price' => '90.00']);

        $response = $this->get('/en/parts/06L906036L');

        $response->assertStatus(200);
    }

    #[Test]
    public function toggling_detail_pages_off_after_a_link_was_indexed_redirects_the_stale_url_to_hub(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();
        $staleUrl = $this->detailUrl($product);

        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'detail_pages_enabled'],
            ['value' => '0', 'type' => 'boolean', 'is_encrypted' => false]
        );
        // SettingsService::getGroup() caches the whole 'seo' group for 5
        // minutes; Product::create() above already triggered a read of it
        // (ProductObserver's IndexNow trigger checks seo.indexnow_enabled)
        // while detail_pages_enabled was still '1' — a raw Eloquent write,
        // unlike SettingsService::set(), never busts that cache itself.
        app(\App\Services\SettingsService::class)->forget('seo');

        $response = $this->get($staleUrl);

        $response->assertStatus(301);
        $response->assertRedirect("/en/parts/{$product->normalized_oem}");
    }

    #[Test]
    public function hreflang_omits_locales_without_a_genuine_translation(): void
    {
        // ProductFactory-style real-world default: only en is populated
        // (de/lt/fr/es all fall back to English via trans_field()) — the
        // detail page must not claim those as genuine alternate-language
        // versions.
        $this->enableDetailPages();
        $product = $this->makeProduct(['name' => ['en' => 'Brake Pad Front']]);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('hreflang="x-default"', false);
        $response->assertDontSee('hreflang="de"', false);
        $response->assertDontSee('hreflang="lt"', false);
        $response->assertDontSee('hreflang="fr"', false);
        $response->assertDontSee('hreflang="es"', false);
    }

    #[Test]
    public function hreflang_includes_a_locale_with_a_genuine_translation(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct(['name' => ['en' => 'Brake Pad Front', 'de' => 'Bremsbelag vorne']]);

        $response = $this->get($this->detailUrl($product));

        $response->assertSee('hreflang="en"', false);
        $response->assertSee('hreflang="de"', false);
        $response->assertDontSee('hreflang="lt"', false);
    }

    #[Test]
    public function canonical_drift_redirects_to_the_current_correct_slug(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();

        // Product renamed after the URL was first generated/indexed — the
        // slug half of the URL is now stale.
        $response = $this->get("/en/parts/{$product->normalized_oem}/{$product->id}-some-stale-slug");

        $response->assertStatus(301);
        $response->assertRedirect($this->detailUrl($product));
    }
}
