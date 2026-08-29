<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The manufacturer page's ItemList JSON-LD was thinner than the product
 * detail page's own Product schema (no image, no mpn, no itemCondition) and
 * linked via the raw, unnormalized oem_number instead of normalized_oem —
 * both fixed here to bring it to parity.
 */
class ManufacturerShowJsonLdTest extends TestCase
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
            'name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true,
        ]);
    }

    #[Test]
    public function item_list_links_use_the_normalized_oem_not_the_raw_punctuated_number(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L-906-036-L', 'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id, 'price' => '100.00',
            'is_in_stock' => true, 'is_active' => true,
        ]);

        $response = $this->get(route('frontend.manufacturer.show', ['lang' => 'en', 'manufacturer' => $this->manufacturer->slug]));

        // The raw, punctuated OEM number is still expected to appear as
        // visible text/labels elsewhere on the page — only the JSON-LD
        // url must use the normalized, canonical form.
        $response->assertSee('"url":"'.url('/en/parts/06L906036L').'"', false);
        $response->assertDontSee('"url":"'.url('/en/parts/06L-906-036-L').'"', false);
    }

    #[Test]
    public function item_list_includes_mpn_and_item_condition(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id, 'price' => '100.00',
            'is_in_stock' => true, 'is_active' => true,
        ]);

        $response = $this->get(route('frontend.manufacturer.show', ['lang' => 'en', 'manufacturer' => $this->manufacturer->slug]));

        $response->assertSee('"mpn":"06L906036L"', false);
        $response->assertSee('"itemCondition":"https://schema.org/NewCondition"', false);
    }

    #[Test]
    public function item_list_uses_the_products_own_featured_image_when_present(): void
    {
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id, 'price' => '100.00',
            'is_in_stock' => true, 'is_active' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id, 'path' => 'product-images/brake.jpg', 'medium_path' => 'product-images/brake-medium.jpg',
            'is_featured' => true, 'sort_order' => 0,
        ]);

        $response = $this->get(route('frontend.manufacturer.show', ['lang' => 'en', 'manufacturer' => $this->manufacturer->slug]));

        $response->assertSee('"image":"'.\Illuminate\Support\Facades\Storage::disk('public')->url('product-images/brake-medium.jpg').'"', false);
    }

    #[Test]
    public function item_list_falls_back_to_the_placeholder_when_a_product_has_no_image_or_manufacturer_logo(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id, 'price' => '100.00',
            'is_in_stock' => true, 'is_active' => true,
        ]);

        $response = $this->get(route('frontend.manufacturer.show', ['lang' => 'en', 'manufacturer' => $this->manufacturer->slug]));

        $response->assertSee('"image":"'.asset('images/product-placeholder.svg').'"', false);
    }
}
