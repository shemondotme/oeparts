<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCrossReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchLogMatchTypeTest extends TestCase
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
            'name' => ['en' => 'Test Manufacturer', 'de' => 'Test Manufacturer', 'lt' => 'Test Manufacturer', 'fr' => 'Test Manufacturer', 'es' => 'Test Manufacturer'],
            'slug' => 'test-manufacturer',
            'country_code' => 'DE',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function exact_match_is_logged_with_exact_type(): void
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
            'match_type' => 'exact',
        ]);
    }

    #[Test]
    public function cross_reference_match_is_logged_with_cross_reference_type(): void
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
        ProductCrossReference::create([
            'product_id' => $product->id,
            'cross_oem_number' => 'XREF999',
            'normalized_cross_oem' => 'XREF999',
        ]);

        $this->get('/en/parts/XREF999');

        $this->assertDatabaseHas('search_logs', [
            'normalized_query' => 'XREF999',
            'match_type' => 'cross_reference',
        ]);
    }

    #[Test]
    public function partial_match_is_logged_with_partial_type(): void
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

        // "906036" is a substring of "06L906036L" but not an exact or
        // cross-reference match — falls through to the partial-match step.
        $this->get('/en/parts/906036');

        $this->assertDatabaseHas('search_logs', [
            'normalized_query' => '906036',
            'match_type' => 'partial',
        ]);
    }

    #[Test]
    public function zero_result_search_leaves_match_type_null(): void
    {
        $this->get('/en/parts/NONEXISTENT123');

        // Zero-result searches log to failed_search_logs, not search_logs —
        // this just confirms no stray search_logs row with a wrong type.
        $this->assertDatabaseMissing('search_logs', [
            'normalized_query' => 'NONEXISTENT123',
        ]);
    }
}
