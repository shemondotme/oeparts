<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => PaymentGateway::Airwallex,
            'transaction_id' => 'pi_'.fake()->unique()->numerify('####################'),
            'status' => PaymentTransactionStatus::Pending,
            'amount' => fake()->numerify('###.##'),
            'gateway_response' => [
                'status' => 'PENDING',
                'created_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Set the payment as succeeded.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentTransactionStatus::Succeeded,
            'gateway_response' => array_merge($attributes['gateway_response'], ['status' => 'SUCCEEDED']),
        ]);
    }

    /**
     * Set the payment as failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentTransactionStatus::Failed,
            'gateway_response' => array_merge($attributes['gateway_response'], ['status' => 'FAILED']),
        ]);
    }
}
