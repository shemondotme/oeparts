<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    protected $model = ShippingMethod::class;

    public function definition(): array
    {
        $minDays = fake()->numberBetween(1, 5);

        return [
            'zone_id'                  => ShippingZone::factory(),
            'name'                     => ['en' => fake()->words(3, true)],
            'description'              => null,
            'flat_rate'                => (string) fake()->randomFloat(2, 2, 25),
            'free_shipping_threshold'  => null,
            'estimated_days_min'       => $minDays,
            'estimated_days_max'       => $minDays + fake()->numberBetween(1, 5),
            'is_active'                => true,
            'sort_order'               => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'flat_rate'               => '0.00',
            'free_shipping_threshold' => '0.00',
        ]);
    }
}
