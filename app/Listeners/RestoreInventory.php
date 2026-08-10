<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Log;

/**
 * Products here are tracked with a single is_in_stock boolean (no quantity
 * column) — UpdateInventory flips it false the moment an order is placed.
 * Cancelling or refunding that order never flipped it back, so a part sold
 * in an order that was later cancelled/refunded stayed permanently hidden
 * as out-of-stock even though nothing was actually delivered.
 */
class RestoreInventory
{
    public function handle(OrderStatusChanged $event): void
    {
        if (! in_array($event->newStatus, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            return;
        }

        try {
            foreach ($event->order->items as $item) {
                $product = $item->product;

                if ($product && ! $product->is_in_stock) {
                    $product->update(['is_in_stock' => true]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to restore inventory for order: ' . $event->order->order_number, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
