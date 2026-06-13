<?php

namespace Database\Factories;

use App\Enums\EmailTemplate;
use App\Enums\LogStatus;
use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    public function definition(): array
    {
        return [
            'to_email'       => fake()->safeEmail(),
            'subject'        => fake()->sentence(),
            'template_type'  => fake()->randomElement(EmailTemplate::cases()),
            'related_id'     => null,
            'related_type'   => null,
            'status'         => LogStatus::Success,
            'error_message'  => null,
            'sent_at'        => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => LogStatus::Failed,
            'error_message' => fake()->sentence(),
        ]);
    }
}
