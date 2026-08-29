<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Models\CarModel;
use App\Models\SearchLog;
use App\Models\FailedSearchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OemSearchTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;
    private CarModel $carModel;
    private Condition $condition;
    private Condition $conditionUsed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $this->conditionUsed = Condition::firstOrCreate(
            ['slug' => 'used'],
            ['name' => 'Used', 'bg_color' => '#fef2f2', 'text_color' => '#991b1b', 'is_active' => true]
        );

        // Create a manufacturer
        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Test Manufacturer', 'de' => 'Test Manufacturer', 'lt' => 'Test Manufacturer', 'fr' => 'Test Manufacturer', 'es' => 'Test Manufacturer'],
            'slug' => 'test-manufacturer',
            'country_code' => 'DE',
            'is_active' => true,
        ]);

        // Create a car model
        $this->carModel = CarModel::create([
            'manufacturer_id' => $this->manufacturer->id,
            'name' => 'Test Model',
            'slug' => 'test-model',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function search_route_returns_200_for_valid_oem(): void
    {
        // Create a product with OEM number
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/en/parts/06L906036L');
        $response->assertStatus(200);
        $response->assertSeeText('06L906036L');
        $response->assertSeeText('Test Manufacturer');
    }

    #[Test]
    public function search_normalizes_oem_and_redirects(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        // Request with dashes and spaces
        $response = $this->get('/en/parts/06L-906-036-L');
        $response->assertRedirect('/en/parts/06L906036L');
        $response->assertStatus(301); // Permanent redirect
    }

    #[Test]
    public function search_finds_cross_reference_match(): void
    {
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        // Add cross-reference
        ProductCrossReference::create([
            'product_id' => $product->id,
            'cross_oem_number' => 'ABC123',
            'normalized_cross_oem' => 'ABC123',
        ]);

        // Search by cross-reference
        $response = $this->get('/en/parts/ABC123');
        $response->assertStatus(200);
        $response->assertSeeText('06L906036L'); // Should show the main OEM
        $response->assertSeeText('ABC123'); // Should mention cross-reference
    }

    #[Test]
    public function search_returns_zero_results_page_when_no_match(): void
    {
        $response = $this->get('/en/parts/NONEXISTENT123');
        // Real 404, not a "soft 404" — this URL genuinely has no matching
        // product; previously returned 200, telling Google this was a
        // valid, permanent page with no content. Content/copy is
        // unchanged, only the status code.
        $response->assertStatus(404);
        // Check that the OEM number appears on the page (in the title or somewhere)
        $response->assertSee('NONEXISTENT123');
        // The page should show some indication of no results (locale-aware)
        $response->assertSee(__('search.zero_heading'), false);
    }

    #[Test]
    public function search_logs_are_created(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $this->get('/en/parts/06L906036L');

        $this->assertDatabaseHas('search_logs', [
            'normalized_query' => '06L906036L',
        ]);
    }

    #[Test]
    public function failed_search_logs_are_created(): void
    {
        $this->get('/en/parts/NONEXISTENT123');

        $this->assertDatabaseHas('failed_search_logs', [
            'normalized_query' => 'NONEXISTENT123',
        ]);
    }

    #[Test]
    public function search_with_manufacturer_filter_works(): void
    {
        $product1 = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $otherManufacturer = Manufacturer::create([
            'name' => ['en' => 'Other Manufacturer', 'de' => 'Other Manufacturer', 'lt' => 'Other Manufacturer', 'fr' => 'Other Manufacturer', 'es' => 'Other Manufacturer'],
            'slug' => 'other-manufacturer',
            'country_code' => 'US',
            'is_active' => true,
        ]);

        $product2 = Product::create([
            'manufacturer_id' => $otherManufacturer->id,
            'oem_number' => '06L906036L', // Same OEM but different manufacturer
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '150.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        // Search without filter should show both
        $response = $this->get('/en/parts/06L906036L');
        $response->assertSeeText('Test Manufacturer');
        $response->assertSeeText('Other Manufacturer');

        // Search with manufacturer filter — result rows are only for that brand; filter chips still list other brands for switching
        $response = $this->get('/en/parts/06L906036L?manufacturer=' . $this->manufacturer->id);
        $response->assertSeeText('Test Manufacturer');
        $response->assertSeeText('€100.00');
        $response->assertDontSee('€150.00');

        // Manufacturer filter active -> buildResultsViewData() populates
        // $breadcrumbs -> the hub page must emit a BreadcrumbList (was
        // previously only hand-rolling an ItemList, never a breadcrumb).
        $response->assertSee('"@type":"BreadcrumbList"', false);
        $response->assertSee('"name":"Test Manufacturer"', false);
    }

    #[Test]
    public function hub_page_hreflang_omits_locales_without_a_genuine_translation(): void
    {
        // Was: unconditionally emitting all 5 locales regardless of the
        // matched product's actual translation completeness.
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Brake Pad Front'],
            'condition_id' => $this->condition->id, 'price' => '100.00',
            'is_in_stock' => true, 'is_active' => true,
        ]);

        $response = $this->get('/en/parts/06L906036L');

        $response->assertSee('hreflang="en"', false);
        $response->assertSee('hreflang="x-default"', false);
        $response->assertDontSee('hreflang="de"', false);
        $response->assertDontSee('hreflang="lt"', false);
    }

    #[Test]
    public function hub_page_item_list_json_ld_links_use_the_normalized_oem_not_the_raw_punctuated_number(): void
    {
        // A real OEM routinely contains hyphens/spaces
        // (OemNormalizerService's own docblock) — the raw oem_number isn't
        // the canonical URL and costs an avoidable extra 301 through
        // NormalizeOemUrl. This file's cross-reference links already
        // preferred normalized_cross_oem; the ItemList entry itself didn't.
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L-906-036-L',
            'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Brake Pad Front'],
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/en/parts/06L906036L');

        $response->assertSee('"url":"'.url('/en/parts/06L906036L').'"', false);
        $response->assertDontSee(url('/en/parts/06L-906-036-L'), false);
    }

    #[Test]
    public function hub_page_omits_breadcrumb_list_when_no_filter_context_is_active(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        // No manufacturer/car-model filter -> $breadcrumbs is empty -> no
        // BreadcrumbList (matches the plan's "only render when non-empty").
        $response = $this->get('/en/parts/06L906036L');

        $response->assertDontSee('"@type":"BreadcrumbList"', false);
    }

    #[Test]
    public function search_with_car_model_filter_works(): void
    {
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        // Attach car model to product
        $product->carModels()->attach($this->carModel->id);

        $response = $this->get('/en/parts/06L906036L?model=' . $this->carModel->id);
        $response->assertStatus(200);
        $response->assertSeeText('06L906036L');
        $response->assertSee(__('search.model_chip', ['name' => 'Test Model']), false);
    }

    #[Test]
    public function autocomplete_endpoint_returns_json(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/en/search/autocomplete?q=06L9');
        $response->assertStatus(200);
        $response->assertJsonStructure([[
            'id', 'oem', 'normalized_oem', 'manufacturer', 'price', 'price_formatted', 'condition', 'url',
        ]]);
    }

    #[Test]
    public function autocomplete_matches_a_cross_reference_number_prefix(): void
    {
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);
        $product->crossReferences()->create([
            'cross_oem_number' => 'XREF999',
            'normalized_cross_oem' => 'XREF999',
        ]);

        // Typing the cross-ref prefix (not the primary OEM) into the live
        // suggestion box previously returned nothing at all.
        $response = $this->get('/en/search/autocomplete?q=XREF9');

        $response->assertStatus(200);
        $response->assertJsonFragment(['oem' => '06L906036L']);
        // Suggestions always lead to the product's own hub page, regardless
        // of which number matched it.
        $response->assertJsonFragment(['url' => route('frontend.search.results', ['lang' => 'en', 'oem' => '06L906036L'])]);
    }

    #[Test]
    public function autocomplete_prioritizes_primary_oem_matches_over_cross_reference_matches(): void
    {
        $primaryProduct = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => 'MATCH001',
            'normalized_oem' => 'MATCH001',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);
        $otherProduct = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => 'OTHER001',
            'normalized_oem' => 'OTHER001',
            'condition_id' => $this->condition->id,
            'price' => '50.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);
        $otherProduct->crossReferences()->create([
            'cross_oem_number' => 'MATCH001-ALT',
            'normalized_cross_oem' => 'MATCH001ALT',
        ]);

        \App\Models\Setting::updateOrCreate(
            ['group' => 'search', 'key' => 'autocomplete_count'],
            ['value' => '1', 'type' => 'string', 'is_encrypted' => false]
        );

        $response = $this->get('/en/search/autocomplete?q=MATCH001');

        // With only one slot available, the primary-OEM match must win.
        $response->assertJsonFragment(['oem' => 'MATCH001']);
        $response->assertJsonMissing(['oem' => 'OTHER001']);
    }

    /**
     * The frontend autocomplete dropdown renders this directly (no VAT/locale
     * math client-side) — must be a ready-to-display string, not a raw decimal.
     */
    #[Test]
    public function autocomplete_price_formatted_includes_a_currency_symbol(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '123.45',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/en/search/autocomplete?q=06L9');
        $response->assertStatus(200);
        $this->assertStringContainsString('€', $response->json()[0]['price_formatted']);
        $this->assertStringContainsString('123.45', $response->json()[0]['price_formatted']);
    }

    #[Test]
    public function autocomplete_requires_minimum_characters(): void
    {
        $response = $this->get('/en/search/autocomplete?q=06');
        $response->assertStatus(200);
        $response->assertJson([]); // Shorter than search.min_chars (default 3)
    }

    #[Test]
    public function invalid_oem_format_returns_404(): void
    {
        // OEM with invalid characters (not A-Z0-9)
        $response = $this->get('/en/parts/06L-906-036-L!');
        $response->assertStatus(404);
    }

    #[Test]
    public function partial_match_page_includes_noindex_robots_meta(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => 'ZZPARTIALDEMO999FULL',
            'normalized_oem' => 'ZZPARTIALDEMO999FULL',
            'condition_id' => $this->condition->id,
            'price' => '50.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/en/parts/PARTIALDEMO999');
        $response->assertStatus(200);
        $response->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    #[Test]
    public function filtered_empty_state_when_condition_excludes_all_results(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => 'FILTERCOND01',
            'normalized_oem' => 'FILTERCOND01',
            'condition_id' => $this->condition->id,
            'price' => '80.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);

        $response = $this->get('/en/parts/FILTERCOND01?condition=used');
        $response->assertStatus(200);
        $response->assertSee(__('search.filtered_empty_title'), false);
    }

    #[Test]
    public function filtered_empty_state_when_in_stock_only_excludes_all_results(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => 'FILTERSTOCK01',
            'normalized_oem' => 'FILTERSTOCK01',
            'condition_id' => $this->condition->id,
            'price' => '90.00',
            'is_in_stock' => false,
            'is_active' => true,
        ]);

        $response = $this->get('/en/parts/FILTERSTOCK01?in_stock=1');
        $response->assertStatus(200);
        $response->assertSee(__('search.filtered_empty_title'), false);
    }

    #[Test]
    public function search_results_shows_more_than_20_matches_on_a_single_page_without_pagination(): void
    {
        // Pagination was removed entirely (see SearchController::results())
        // — up to search.results_limit (default 100) now renders on ONE
        // page, closing the "page 2+ never gets a crawl signal" gap
        // (robots.txt blocked ?page=, canonical always collapsed to page 1)
        // rather than patching it.
        for ($i = 0; $i < 21; $i++) {
            Product::create([
                'manufacturer_id' => $this->manufacturer->id,
                'oem_number' => 'PAGETEST01-' . $i,
                'normalized_oem' => 'PAGETEST01',
                'condition_id' => $this->condition->id,
                'price' => '100.00',
                'is_in_stock' => true,
                'is_active' => true,
            ]);
        }

        $response = $this->get('/en/parts/PAGETEST01');
        $response->assertStatus(200);
        // The 21st result (would have needed page 2 under the old 20/page
        // limit) is visible on the single unpaginated page.
        $response->assertSee('PAGETEST01-20', false);
    }

    #[Test]
    public function search_results_page_contains_no_pagination_markup_even_with_more_than_100_matches(): void
    {
        for ($i = 0; $i < 105; $i++) {
            Product::create([
                'manufacturer_id' => $this->manufacturer->id,
                'oem_number' => 'BIGSET01-' . $i,
                'normalized_oem' => 'BIGSET01',
                'condition_id' => $this->condition->id,
                'price' => '100.00',
                'is_in_stock' => true,
                'is_active' => true,
            ]);
        }

        $response = $this->get('/en/parts/BIGSET01');

        $response->assertStatus(200);
        $response->assertDontSee('rel="next"', false);
        $response->assertDontSee('rel="prev"', false);
        // Capped at search.results_limit (default 100) — the 101st+ rows
        // genuinely aren't rendered, but the page itself must not paginate
        // to reach them; that cap is a separate, pre-existing concern.
    }
}
