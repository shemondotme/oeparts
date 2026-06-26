<?php

namespace Database\Factories;

use App\Enums\PartInquiryStatus;
use App\Models\PartInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartInquiry>
 */
class PartInquiryFactory extends Factory
{
    protected $model = PartInquiry::class;

    public function definition(): array
    {
        return [
            'failed_search_log_id' => null,
            'email'                => fake()->safeEmail(),
            'phone'                => null,
            'oem_number'           => strtoupper(fake()->bothify('??####??')),
            'manufacturer'         => fake()->company(),
            'car_model'            => fake()->word().' '.fake()->randomDigit(),
            'year'                 => fake()->numberBetween(2005, 2025),
            'vin_number'           => null,
            'quantity'             => fake()->numberBetween(1, 10),
            'urgency'              => fake()->randomElement(['normal', 'soon', 'urgent']),
            'notes'                => fake()->optional()->sentence(),
            'status'               => PartInquiryStatus::New,
            'admin_note'           => null,
            'ip_address'           => fake()->ipv4(),
        ];
    }

    public function reviewing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PartInquiryStatus::Reviewing,
        ]);
    }

    public function sourced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PartInquiryStatus::Sourced,
        ]);
    }
}
