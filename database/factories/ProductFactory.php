<?php

namespace Database\Factories;

use App\Models\CarModel;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $oem = $this->faker->unique()->regexify('[A-Z0-9]{8,12}');

        return [
            'manufacturer_id' => Manufacturer::inRandomOrder()->first()?->id ?? Manufacturer::factory(),
            'oem_number' => $oem,
            'normalized_oem' => strtoupper(preg_replace('/[^A-Z0-9]/', '', $oem)),
            'name' => [
                'en' => fake()->words(3, true),
                'de' => fake()->words(3, true),
            ],
            'description' => [
                'en' => fake()->sentence(),
                'de' => fake()->sentence(),
            ],
            'condition_id' => Condition::inRandomOrder()->first()?->id ?? Condition::firstOrCreate(
                ['slug' => 'new'],
                ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
            )->id,
            'price' => fake()->numerify('###.##'),
            'delivery_time' => fake()->numerify('# days'),
            'moq' => fake()->numberBetween(1, 10),
            'is_in_stock' => true,
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_in_stock' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function condition(int $conditionId): static
    {
        return $this->state(fn (array $attributes) => [
            'condition_id' => $conditionId,
        ]);
    }

    /**
     * Populates all 5 locales (base definition() only ever sets en/de) —
     * needed to test the "genuine translation present" branch of the
     * locale-completeness check (SeoService::hasRealTranslation()) against
     * a fully-translated product, as opposed to the untranslated-lt/fr/es
     * default every other test relies on to exercise the fallback branch.
     */
    public function withFullTranslations(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => [
                'en' => fake()->words(3, true),
                'de' => fake()->words(3, true),
                'lt' => fake()->words(3, true),
                'fr' => fake()->words(3, true),
                'es' => fake()->words(3, true),
            ],
            'description' => [
                'en' => fake()->sentence(),
                'de' => fake()->sentence(),
                'lt' => fake()->sentence(),
                'fr' => fake()->sentence(),
                'es' => fake()->sentence(),
            ],
        ]);
    }

    public function withCrossReferences(int $count = 2): static
    {
        return $this->afterCreating(function (Product $product) use ($count) {
            for ($i = 0; $i < $count; $i++) {
                $oem = $this->faker->unique()->regexify('[A-Z0-9]{8,12}');
                ProductCrossReference::create([
                    'product_id' => $product->id,
                    'cross_oem_number' => $oem,
                    'normalized_cross_oem' => $oem,
                ]);
            }
        });
    }

    public function withCarModels(int $count = 1): static
    {
        return $this->afterCreating(function (Product $product) use ($count) {
            $product->carModels()->attach(
                CarModel::factory()->count($count)->create(['manufacturer_id' => $product->manufacturer_id])->pluck('id')
            );
        });
    }

    /**
     * One featured image + $additional gallery images.
     */
    public function withImages(int $additional = 1): static
    {
        return $this->afterCreating(function (Product $product) use ($additional) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => 'product-images/featured-placeholder.jpg',
                'is_featured' => true,
                'sort_order' => 0,
            ]);

            for ($i = 1; $i <= $additional; $i++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => "product-images/gallery-{$i}-placeholder.jpg",
                    'is_featured' => false,
                    'sort_order' => $i,
                ]);
            }
        });
    }
}
