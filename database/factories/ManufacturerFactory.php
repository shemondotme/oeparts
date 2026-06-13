<?php

namespace Database\Factories;

use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manufacturer>
 */
class ManufacturerFactory extends Factory
{
    protected $model = Manufacturer::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name'            => ['en' => $name, 'de' => $name],
            'slug'            => fake()->slug(),
            'country_code'    => fake()->randomElement(['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PL', 'CZ', 'SE']),
            'is_active'       => true,
            'is_verified_oem' => fake()->boolean(80),
            'sort_order'      => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified_oem' => true,
        ]);
    }
}
