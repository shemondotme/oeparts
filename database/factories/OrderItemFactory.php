<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->numerify('##.##');
        $quantity  = fake()->numberBetween(1, 10);

        return [
            'order_id'             => Order::factory(),
            'product_id'           => Product::factory(),
            'oem_number_snapshot'  => strtoupper(fake()->bothify('??####??')),
            'manufacturer_snapshot' => fake()->company(),
            'condition_snapshot'   => fake()->randomElement(['new', 'used']),
            'quantity'             => $quantity,
            'unit_price'           => $unitPrice,
            'total_price'          => bcmul($unitPrice, $quantity, 2),
        ];
    }
}
