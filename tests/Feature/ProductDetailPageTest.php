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

    /**
     * Same raw-write + SettingsService::forget() pattern as enableDetailPages()
     * above, generalized to any group/key — a raw Eloquent write never busts
     * SettingsService::getGroup()'s 5-minute per-group cache on its own.
     */
    private function setSetting(string $group, string $key, string $value, string $type = 'boolean'): void
    {
        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => $type, 'is_encrypted' => false]
        );
        app(\App\Services\SettingsService::class)->forget($group);
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
    public function detail_page_shows_confirmed_vehicle_fitment_when_car_models_are_linked(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();
        $carManufacturer = Manufacturer::create([
            'name' => ['en' => 'Audi', 'de' => 'Audi', 'lt' => 'Audi', 'fr' => 'Audi', 'es' => 'Audi'],
            'slug' => 'audi', 'country_code' => 'DE', 'is_active' => true,
        ]);
        $carModel = \App\Models\CarModel::create([
            'manufacturer_id' => $carManufacturer->id,
            'name' => 'A4 (B9)', 'slug' => 'a4-b9',
            'year_from' => 2016, 'year_to' => 2019, 'is_active' => true,
        ]);
        $product->carModels()->attach($carModel->id);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSeeText('Confirmed Vehicle Fitment');
        $response->assertSeeText('Audi A4 (B9)');
        $response->assertSeeText('2016');
    }

    #[Test]
    public function detail_page_omits_the_fitment_section_when_no_car_models_are_linked(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertDontSeeText('Confirmed Vehicle Fitment');
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
    public function soft_deleted_product_redirects_to_the_manufacturer_page_via_its_own_fallback_redirect(): void
    {
        // ProductObserver::createFallbackRedirects() now proactively creates
        // a stored Redirect the moment a product is deleted, and
        // HandleRedirects intercepts the request before it ever reaches
        // this controller's own redirectToHub() fallback below. This is a
        // deliberate improvement over redirecting to the OEM hub page,
        // which can itself 404 when the deleted product was the last
        // active one for that OEM — exactly the case here (this test's
        // product is the only one with this OEM).
        $this->enableDetailPages();
        $product = $this->makeProduct();
        $url = $this->detailUrl($product);
        $product->delete();

        $response = $this->get($url);

        $response->assertStatus(301);
        $response->assertRedirect(route('frontend.manufacturer.show', ['lang' => 'en', 'manufacturer' => $this->manufacturer->slug]));
    }

    #[Test]
    public function a_hard_deleted_product_with_no_stored_fallback_redirect_still_falls_back_to_the_hub_via_the_controller(): void
    {
        // Defense in depth: if the observer's redirect creation never ran
        // (e.g. bypassed a save entirely) or was itself deleted, the
        // controller's own is_active/trashed check still 301s to the hub
        // rather than ever rendering a gone product.
        $this->enableDetailPages();
        $product = $this->makeProduct();
        $url = $this->detailUrl($product);
        $product->delete();
        \App\Models\Redirect::where('from_url', 'like', '%'.$product->normalized_oem.'%')->delete();

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
    public function hreflang_for_another_locale_points_at_that_locales_own_slug_not_the_current_pages(): void
    {
        // The idSlug's cosmetic text is derived from the product's name IN
        // THE CURRENT locale — reusing the current route's idSlug verbatim
        // for every other locale's hreflang tag pointed "de" at a URL still
        // carrying the ENGLISH slug text. Following it would 301 again
        // (canonical-drift check below) to the real German-slugged URL —
        // an hreflang link that redirects instead of resolving directly.
        $this->enableDetailPages();
        $product = $this->makeProduct(['name' => ['en' => 'Brake Pad Front', 'de' => 'Bremsbelag vorne']]);

        $response = $this->get($this->detailUrl($product, 'en'));

        $germanUrl = $this->detailUrl($product, 'de');
        $response->assertSee('hreflang="de" href="'.url($germanUrl).'"', false);
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

    #[Test]
    public function detail_page_shows_vat_inclusive_price_when_configured(): void
    {
        $this->enableDetailPages();
        $this->setSetting('tax', 'price_display', 'inc_vat', 'string');
        $this->setSetting('tax', 'default_vat_rate', '20', 'integer');
        $product = $this->makeProduct(['price' => '100.00']);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSeeText('incl. VAT');
    }

    #[Test]
    public function detail_page_shows_vat_exclusive_price_when_configured(): void
    {
        $this->enableDetailPages();
        $this->setSetting('tax', 'price_display', 'exc_vat', 'string');
        $product = $this->makeProduct(['price' => '100.00']);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSeeText('excl. VAT');
    }

    #[Test]
    public function manufacturer_trust_block_shows_verified_oem_badge(): void
    {
        $this->enableDetailPages();
        $this->manufacturer->update(['is_verified_oem' => true]);
        $product = $this->makeProduct();

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSeeText('Verified OEM Manufacturer');
    }

    #[Test]
    public function specifications_table_shown_when_present_and_toggle_on(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct(['specifications' => ['Weight' => '2.4 kg']]);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSeeText('Weight');
        $response->assertSeeText('2.4 kg');
    }

    #[Test]
    public function specifications_table_hidden_when_empty(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertDontSeeText('Specifications');
    }

    #[Test]
    public function specifications_table_hidden_when_toggle_off(): void
    {
        $this->enableDetailPages();
        $this->setSetting('pdp', 'show_specifications', '0');
        $product = $this->makeProduct(['specifications' => ['Weight' => '2.4 kg']]);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertDontSeeText('2.4 kg');
    }

    #[Test]
    public function warranty_block_shown_when_warranty_months_set(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct(['warranty_months' => 12]);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSeeText('12 months warranty');
    }

    #[Test]
    public function warranty_block_hidden_without_warranty_months(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertDontSeeText('Warranty');
    }

    #[Test]
    public function video_section_shown_when_video_url_set_and_toggle_on(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct(['video_url' => 'https://example.com/video.mp4']);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSee('https://example.com/video.mp4', false);
    }

    #[Test]
    public function video_section_respects_toggle(): void
    {
        $this->enableDetailPages();
        $this->setSetting('pdp', 'show_video', '0');
        $product = $this->makeProduct(['video_url' => 'https://example.com/video.mp4']);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertDontSee('https://example.com/video.mp4', false);
    }

    #[Test]
    public function related_products_excludes_self_and_shows_same_manufacturer_match(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct(['oem_number' => 'AAA111', 'normalized_oem' => 'AAA111']);
        $related = $this->makeProduct(['oem_number' => 'BBB222', 'normalized_oem' => 'BBB222', 'name' => ['en' => 'Brake Pad Rear']]);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSeeText('Related Products');
        $response->assertSeeText('BBB222');
        $response->assertSeeTextInOrder(['Related Products', 'BBB222']);
    }

    #[Test]
    public function related_products_section_respects_toggle(): void
    {
        $this->enableDetailPages();
        $this->setSetting('pdp', 'show_related_products', '0');
        $product = $this->makeProduct(['oem_number' => 'AAA111', 'normalized_oem' => 'AAA111']);
        $this->makeProduct(['oem_number' => 'BBB222', 'normalized_oem' => 'BBB222']);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertDontSeeText('Related Products');
    }

    #[Test]
    public function only_approved_reviews_render_on_detail_page(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();
        $product->reviews()->create(['reviewer_name' => 'Pending Pete', 'comment' => 'pending review text', 'rating' => 4, 'status' => 'pending']);
        $product->reviews()->create(['reviewer_name' => 'Rejected Rita', 'comment' => 'rejected review text', 'rating' => 2, 'status' => 'rejected']);
        $product->reviews()->create(['reviewer_name' => 'Approved Alex', 'comment' => 'approved review text', 'rating' => 5, 'status' => 'approved']);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSeeText('Approved Alex');
        $response->assertDontSeeText('Pending Pete');
        $response->assertDontSeeText('Rejected Rita');
    }

    #[Test]
    public function reviews_section_respects_toggle(): void
    {
        $this->enableDetailPages();
        $this->setSetting('pdp', 'show_reviews', '0');
        $product = $this->makeProduct();
        $product->reviews()->create(['reviewer_name' => 'Approved Alex', 'comment' => 'approved review text', 'rating' => 5, 'status' => 'approved']);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertDontSeeText('Approved Alex');
        $response->assertDontSeeText('Customer Reviews');
    }

    #[Test]
    public function buy_now_button_hidden_when_toggle_off(): void
    {
        $this->enableDetailPages();
        $product = $this->makeProduct();

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertDontSee('product-buy-now', false);
    }

    #[Test]
    public function buy_now_button_shown_when_toggle_on_and_in_stock(): void
    {
        $this->enableDetailPages();
        $this->setSetting('pdp', 'buy_now_enabled', '1');
        $product = $this->makeProduct(['is_in_stock' => true]);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertSee('product-buy-now', false);
    }

    #[Test]
    public function buy_now_button_hidden_when_out_of_stock_even_if_toggle_on(): void
    {
        $this->enableDetailPages();
        $this->setSetting('pdp', 'buy_now_enabled', '1');
        $product = $this->makeProduct(['is_in_stock' => false]);

        $response = $this->get($this->detailUrl($product));

        $response->assertStatus(200);
        $response->assertDontSee('product-buy-now', false);
    }
}
