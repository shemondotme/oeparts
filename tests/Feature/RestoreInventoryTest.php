<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Products carry a single is_in_stock boolean (no quantity column) that
 * UpdateInventory flips false the moment an order is placed. Cancelling or
 * refunding that order never flipped it back — a part sold in an order that
 * was later cancelled/refunded stayed permanently hidden as out-of-stock
 * even though it was never actually delivered to anyone.
 */
class RestoreInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function outOfStockProduct(): Product
    {
        return Product::factory()->create(['is_in_stock' => false]);
    }

    #[Test]
    public function cancelling_an_order_restores_stock_for_its_products(): void
    {
        $product = $this->outOfStockProduct();
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        app(OrderService::class)->cancelOrder($order);

        $this->assertTrue($product->fresh()->is_in_stock);
    }

    #[Test]
    public function refunding_an_order_restores_stock_for_its_products(): void
    {
        $product = $this->outOfStockProduct();
        $order = Order::factory()->create(['status' => OrderStatus::RefundRequested]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        app(OrderService::class)->transitionStatus($order, OrderStatus::Refunded);

        $this->assertTrue($product->fresh()->is_in_stock);
    }

    #[Test]
    public function other_status_transitions_do_not_touch_stock(): void
    {
        $product = $this->outOfStockProduct();
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        app(OrderService::class)->transitionStatus($order, OrderStatus::Paid);

        $this->assertFalse($product->fresh()->is_in_stock);
    }

    #[Test]
    public function already_in_stock_products_are_left_untouched_on_cancellation(): void
    {
        $product = Product::factory()->create(['is_in_stock' => true]);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        app(OrderService::class)->cancelOrder($order);

        $this->assertTrue($product->fresh()->is_in_stock);
    }
}
