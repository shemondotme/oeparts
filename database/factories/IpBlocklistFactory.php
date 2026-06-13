<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\IpBlocklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpBlocklist>
 */
class IpBlocklistFactory extends Factory
{
    protected $model = IpBlocklist::class;

    public function definition(): array
    {
        return [
            'ip_address'  => fake()->ipv4(),
            'reason'      => fake()->sentence(),
            'blocked_by'  => Admin::factory(),
            'expires_at'  => fake()->dateTimeBetween('+1 week', '+6 months'),
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }
}
