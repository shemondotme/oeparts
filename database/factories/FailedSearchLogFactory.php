<?php

namespace Database\Factories;

use App\Models\FailedSearchLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailedSearchLog>
 */
class FailedSearchLogFactory extends Factory
{
    protected $model = FailedSearchLog::class;

    public function definition(): array
    {
        $query = fake()->word().' '.fake()->numerify('#####');

        return [
            'search_query'      => $query,
            'normalized_query'  => strtoupper(preg_replace('/[^A-Z0-9]/', '', $query)),
            'lang'              => fake()->randomElement(['en', 'de']),
            'user_id'           => null,
            'ip_address'        => fake()->ipv4(),
            'inquiry_submitted' => false,
        ];
    }

    public function withUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
        ]);
    }

    public function withInquiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'inquiry_submitted' => true,
        ]);
    }
}
