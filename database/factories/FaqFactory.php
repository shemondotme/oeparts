<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        $question = fake()->sentence();

        return [
            'category'   => fake()->randomElement(['shipping', 'ordering', 'returns', 'payment', 'products', 'general']),
            'question'   => ['en' => $question, 'de' => $question],
            'answer'     => ['en' => fake()->paragraph(), 'de' => fake()->paragraph()],
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active'  => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
