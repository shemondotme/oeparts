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
        $response->assertStatus(200);
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
            'id', 'oem', 'normalized_oem', 'manufacturer', 'price', 'condition', 'url',
        ]]);
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
    public function search_results_paginate_second_page(): void
    {
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

        $response = $this->get('/en/parts/PAGETEST01?page=2');
        $response->assertStatus(200);
        $response->assertSee('PAGETEST01-20', false);
    }
}
