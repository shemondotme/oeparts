<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Support\Facades\Log;

class UpdateInventory
{
    public function handle(OrderPlaced $event): void
    {
        try {
            foreach ($event->order->items as $item) {
                $product = $item->product;

                if ($product && $product->is_in_stock) {
                    $product->update(['is_in_stock' => false]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to update inventory for order: ' . $event->order->order_number, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
