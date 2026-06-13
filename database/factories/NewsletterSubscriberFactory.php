<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    public function definition(): array
    {
        return [
            'email'           => fake()->unique()->safeEmail(),
            'lang'            => fake()->randomElement(['en', 'de', 'fr', 'nl']),
            'is_active'       => true,
            'subscribed_at'   => fake()->dateTimeBetween('-1 year', 'now'),
            'unsubscribed_at' => null,
            'ip_address'      => fake()->ipv4(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active'       => false,
            'unsubscribed_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
