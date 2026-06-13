<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Enums\ContactSubjectType;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    public function definition(): array
    {
        return [
            'name'          => fake()->name(),
            'email'         => fake()->safeEmail(),
            'subject_type'  => fake()->randomElement(ContactSubjectType::cases()),
            'order_number'  => null,
            'oem_number'    => null,
            'manufacturer'  => null,
            'car_model'     => null,
            'year'          => null,
            'vin_number'    => null,
            'message'       => fake()->paragraph(),
            'status'        => ContactStatus::Unread,
            'otp_verified'  => false,
            'ip_address'    => fake()->ipv4(),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactStatus::Read,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactStatus::Resolved,
        ]);
    }
}
