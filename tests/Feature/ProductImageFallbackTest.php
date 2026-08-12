<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\MediaFile;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImageFallbackTest extends TestCase
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

    private function makeProduct(): Product
    {
        return Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Brake Pad Front'],
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function slug_is_auto_populated_on_create(): void
    {
        $product = $this->makeProduct();

        $this->assertSame('bosch-brake-pad-front-new', $product->fresh()->slug);
    }

    #[Test]
    public function slug_is_refreshed_when_name_changes(): void
    {
        $product = $this->makeProduct();

        $product->update(['name' => ['en' => 'Brake Pad Rear']]);

        $this->assertSame('bosch-brake-pad-rear-new', $product->fresh()->slug);
    }

    #[Test]
    public function slug_is_not_refreshed_for_an_unrelated_field_change(): void
    {
        $product = $this->makeProduct();
        $originalSlug = $product->fresh()->slug;

        $product->update(['price' => '150.00']);

        $this->assertSame($originalSlug, $product->fresh()->slug);
    }

    #[Test]
    public function featured_image_wins_over_manufacturer_logo(): void
    {
        $product = $this->makeProduct();
        $logo = MediaFile::create([
            'uploaded_by' => \App\Models\Admin::factory()->create()->id,
            'file_name' => 'bosch-logo.png', 'file_path' => 'logos/bosch-logo.png',
            'file_url' => '', 'mime_type' => 'image/png', 'size' => 1024,
        ]);
        $this->manufacturer->update(['logo_id' => $logo->id]);
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'product-images/featured.jpg',
            'is_featured' => true,
        ]);

        $this->assertStringContainsString('featured.jpg', $product->fresh()->resolvedImageUrl());
    }

    #[Test]
    public function manufacturer_logo_wins_over_placeholder_when_no_image_uploaded(): void
    {
        $product = $this->makeProduct();
        $logo = MediaFile::create([
            'uploaded_by' => \App\Models\Admin::factory()->create()->id,
            'file_name' => 'bosch-logo.png', 'file_path' => 'logos/bosch-logo.png',
            'file_url' => '', 'mime_type' => 'image/png', 'size' => 1024,
        ]);
        $this->manufacturer->update(['logo_id' => $logo->id]);

        $this->assertStringContainsString('bosch-logo.png', $product->fresh()->resolvedImageUrl());
    }

    #[Test]
    public function placeholder_is_used_when_neither_image_nor_logo_exists(): void
    {
        $product = $this->makeProduct();

        $this->assertStringContainsString('product-placeholder.svg', $product->fresh()->resolvedImageUrl());
    }
}
