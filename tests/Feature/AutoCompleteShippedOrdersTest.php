<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AutoCompleteShippedOrders used to match ANY historical "became Shipped"
 * row past the cutoff, not the order's MOST RECENT one — an order shipped
 * long ago, moved back to Processing (e.g. a shipping issue), and re-shipped
 * just yesterday still had its old, stale "Shipped" row from months back,
 * so the command could wrongly auto-complete a shipment that just went out.
 */
class AutoCompleteShippedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function history(Order $order, OrderStatus $newStatus, \DateTimeInterface $at): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id, 'old_status' => OrderStatus::Pending->value, 'new_status' => $newStatus->value,
        ])->forceFill(['created_at' => $at])->save();
    }

    #[Test]
    public function an_order_reshipped_recently_is_not_auto_completed_off_a_stale_history_row(): void
    {
        Setting::updateOrCreate(['group' => 'orders', 'key' => 'auto_complete_days'], ['value' => '3', 'type' => 'integer']);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped]);
        $this->history($order, OrderStatus::Shipped, now()->subDays(30));
        $this->history($order, OrderStatus::Processing, now()->subDay());
        $this->history($order, OrderStatus::Shipped, now()->subHours(2)); // re-shipped recently

        Artisan::call('oeparts:orders:auto-complete');

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }

    #[Test]
    public function an_order_shipped_long_enough_ago_is_still_auto_completed(): void
    {
        Setting::updateOrCreate(['group' => 'orders', 'key' => 'auto_complete_days'], ['value' => '3', 'type' => 'integer']);

        $order = Order::factory()->create(['status' => OrderStatus::Shipped]);
        $this->history($order, OrderStatus::Shipped, now()->subDays(10));

        Artisan::call('oeparts:orders:auto-complete');

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }
}
