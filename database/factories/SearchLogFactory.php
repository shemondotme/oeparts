<?php

namespace Database\Factories;

use App\Models\Manufacturer;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchLog>
 */
class SearchLogFactory extends Factory
{
    protected $model = SearchLog::class;

    public function definition(): array
    {
        $query = fake()->word().' '.fake()->numerify('#####');

        return [
            'search_query'    => $query,
            'normalized_query' => strtoupper(preg_replace('/[^A-Z0-9]/', '', $query)),
            'result_count'    => fake()->numberBetween(0, 100),
            'manufacturer_id' => null,
            'car_model_id'    => null,
            'lang'            => fake()->randomElement(['en', 'de']),
            'user_id'         => null,
            'ip_address'      => fake()->ipv4(),
        ];
    }

    public function withUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
        ]);
    }

    public function noResults(): static
    {
        return $this->state(fn (array $attributes) => [
            'result_count' => 0,
        ]);
    }
}
