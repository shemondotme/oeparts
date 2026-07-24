<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.fake()->unique()->numberBetween(100000, 999999),
            'user_id' => User::factory(),
            'guest_email' => null,
            'status' => OrderStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'payment_status' => PaymentStatus::Pending,
            'payment_reference' => null,
            'subtotal' => '150.00',
            'discount_amount' => '0.00',
            'shipping_cost' => '10.00',
            'vat_amount' => '31.50',
            'grand_total' => '191.50',
            'coupon_id' => null,
            'shipping_method_id' => null,
            'shipping_method_name_snapshot' => 'Standard Shipping',
            'shipping_estimated_days_min' => 3,
            'shipping_estimated_days_max' => 5,
            'shipping_name' => fake()->name(),
            'shipping_address_line1' => fake()->streetAddress(),
            'shipping_city' => fake()->city(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_country_code' => 'DE',
            'is_b2b' => false,
            'company_name' => null,
            'vat_number' => null,
            'vat_exempt' => false,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_content' => null,
            'customer_note' => null,
            'ip_address' => fake()->ipv4(),
            'tracking_number' => null,
            'carrier' => null,
            'urgent_processing' => false,
            'urgent_processing_fee' => '0.00',
            'invoice_number' => null,
        ];
    }

    public function b2b(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_b2b' => true,
            'company_name' => fake()->company(),
            'vat_number' => 'DE'.fake()->numerify('##########'),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Completed,
            'payment_reference' => 'pi_'.fake()->unique()->numerify('####################'),
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Shipped,
            'tracking_number' => fake()->unique()->numerify('1Z###########'),
            'carrier' => 'UPS',
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_email' => fake()->safeEmail(),
        ]);
    }
}
