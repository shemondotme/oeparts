<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code'                 => strtoupper(fake()->unique()->bothify('??####')),
            'name'                 => fake()->words(3, true),
            'discount_type'        => fake()->randomElement([DiscountType::Percentage, DiscountType::Fixed]),
            'discount_value'       => fake()->numerify('##.##'),
            'min_order_amount'     => fake()->numerify('###.##'),
            'usage_limit'          => fake()->numberBetween(10, 1000),
            'usage_limit_per_user' => 1,
            'expires_at'           => fake()->dateTimeBetween('+1 week', '+1 year'),
            'is_active'            => true,
            'created_by'           => null,
        ];
    }

    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type'  => DiscountType::Percentage,
            'discount_value' => fake()->numberBetween(5, 50),
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type'  => DiscountType::Fixed,
            'discount_value' => fake()->numerify('##.##'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
