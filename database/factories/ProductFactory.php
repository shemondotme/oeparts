<?php

namespace Database\Factories;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Set the product as out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_in_stock' => false,
        ]);
    }

    /**
     * Set the product as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set specific condition.
     */
    public function condition(int $conditionId): static
    {
        return $this->state(fn (array $attributes) => [
            'condition_id' => $conditionId,
        ]);
    }
}
