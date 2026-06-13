<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        $quote = fake()->sentence(10);

        return [
            'name'       => fake()->name(),
            'company'    => fake()->company(),
            'location'   => fake()->city().', '.fake()->country(),
            'quote'      => ['en' => $quote, 'de' => $quote],
            'rating'     => fake()->numberBetween(1, 5),
            'is_active'  => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
