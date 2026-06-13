<?php

namespace Database\Factories;

use App\Enums\RedirectType;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    public function definition(): array
    {
        return [
            'from_url'  => '/old-'.fake()->slug(),
            'to_url'    => '/new-'.fake()->slug(),
            'type'      => fake()->randomElement([RedirectType::Permanent, RedirectType::Temporary]),
            'is_active' => true,
            'hit_count' => 0,
        ];
    }

    public function permanent(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RedirectType::Permanent,
        ]);
    }

    public function temporary(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RedirectType::Temporary,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
