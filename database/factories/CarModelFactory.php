<?php

namespace Database\Factories;

use App\Models\CarModel;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarModel>
 */
class CarModelFactory extends Factory
{
    protected $model = CarModel::class;

    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->randomDigit();

        return [
            'manufacturer_id' => Manufacturer::factory(),
            'name'            => $name,
            'slug'            => fake()->unique()->slug(),
            'year_from'       => fake()->numberBetween(2000, 2020),
            'year_to'         => fake()->numberBetween(2021, 2025),
            'is_active'       => true,
            'sort_order'      => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
