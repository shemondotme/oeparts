<?php

namespace Database\Factories;

use App\Models\NewsletterCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterCampaign>
 */
class NewsletterCampaignFactory extends Factory
{
    protected $model = NewsletterCampaign::class;

    public function definition(): array
    {
        return [
            'subject'        => fake()->sentence(4),
            'html_content'   => '<html><body><h1>'.fake()->sentence().'</h1><p>'.fake()->paragraph().'</p></body></html>',
            'plain_content'  => fake()->sentence()."\n\n".fake()->paragraph(),
            'status'         => 'draft',
            'sent_count'     => 0,
            'failed_count'   => 0,
            'scheduled_at'   => null,
            'sent_at'        => null,
            'created_by'     => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'    => 'sent',
            'sent_at'   => fake()->dateTimeBetween('-1 month', 'now'),
            'sent_count' => fake()->numberBetween(10, 5000),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'scheduled',
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+1 month'),
        ]);
    }
}
