<?php

namespace Tests\Unit;

use App\Models\CarModel;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductDescriptionFallbackTest extends TestCase
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

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function manual_description_wins_when_present(): void
    {
        $product = $this->makeProduct([
            'description' => ['en' => 'A genuinely unique, hand-written description.'],
            'delivery_time' => '3 days',
            'moq' => 5,
        ]);

        $this->assertSame('A genuinely unique, hand-written description.', $product->descriptionOrFallback('en'));
    }

    #[Test]
    public function two_products_with_different_facts_produce_different_fallback_strings(): void
    {
        $productA = $this->makeProduct(['delivery_time' => '2 days', 'moq' => 1]);
        $productB = $this->makeProduct(['delivery_time' => '10 days', 'moq' => 50]);

        $descriptionA = $productA->descriptionOrFallback('en');
        $descriptionB = $productB->descriptionOrFallback('en');

        // This is what keeps the fallback out of "scaled content abuse"
        // territory — a fixed, unvarying sentence would be identical here.
        $this->assertNotSame($descriptionA, $descriptionB);
        $this->assertStringContainsString('2 days', $descriptionA);
        $this->assertStringContainsString('10 days', $descriptionB);
    }

    #[Test]
    public function fallback_includes_car_model_fitment_when_present(): void
    {
        $product = $this->makeProduct();
        $carModel = CarModel::factory()->create(['manufacturer_id' => $this->manufacturer->id, 'name' => 'Golf VII']);
        $product->carModels()->attach($carModel->id);

        $description = $product->descriptionOrFallback('en');

        $this->assertStringContainsString('Golf VII', $description);
    }

    #[Test]
    public function fallback_is_never_empty_even_with_no_optional_facts_populated(): void
    {
        $product = $this->makeProduct();

        $description = $product->descriptionOrFallback('en');

        $this->assertNotSame('', trim($description));
        $this->assertStringContainsString($product->oem_number, $description);
    }

    #[Test]
    public function fallback_includes_cross_reference_count_when_present(): void
    {
        $product = $this->makeProduct();
        $product->crossReferences()->createMany([
            ['cross_oem_number' => 'A1', 'normalized_cross_oem' => 'A1'],
            ['cross_oem_number' => 'A2', 'normalized_cross_oem' => 'A2'],
        ]);

        $this->assertStringContainsString('2', $product->descriptionOrFallback('en'));
    }

    #[Test]
    public function fallback_respects_an_admin_configured_template(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'auto_description_template'],
            ['value' => json_encode(['en' => 'Custom: {oem} by {manufacturer}, {condition}.']), 'type' => 'json', 'is_encrypted' => false]
        );
        app(\App\Services\SettingsService::class)->forget('seo');

        $product = $this->makeProduct();

        $this->assertSame('Custom: 06L906036L by Bosch, New.', $product->descriptionOrFallback('en'));
    }
}
