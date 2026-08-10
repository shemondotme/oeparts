<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\OrderService;
use Illuminate\Console\Command;

class AutoCompleteShippedOrders extends Command
{
    protected $signature = 'oeparts:orders:auto-complete';

    protected $description = 'Mark shipped orders as delivered after the operator-configured number of days';

    public function handle(OrderService $orders): int
    {
        $days = (int) settings('orders.auto_complete_days', 0);

        if ($days <= 0) {
            $this->info('Auto-complete disabled (orders.auto_complete_days is 0).');

            return self::SUCCESS;
        }

        // Correlates on each order's MOST RECENT transition into Shipped,
        // not just ANY historical one — an order that was shipped, moved
        // back to Processing (e.g. a shipping issue), and re-shipped
        // recently still has its old, past-cutoff "Shipped" history row.
        // Matching on any row let a same-day re-ship get auto-completed
        // within a day purely because of that stale first shipment.
        $due = Order::query()
            ->where('status', OrderStatus::Shipped)
            ->whereIn('id', OrderStatusHistory::query()
                ->select('order_id')
                ->where('new_status', OrderStatus::Shipped->value)
                ->groupBy('order_id')
                ->havingRaw('MAX(created_at) <= ?', [now()->subDays($days)]))
            ->get();

        foreach ($due as $order) {
            $orders->transitionStatus(
                $order,
                OrderStatus::Delivered,
                "Auto-completed {$days} days after shipping.",
            );
            $this->info("Order {$order->order_number} marked delivered.");
        }

        if ($due->isEmpty()) {
            $this->info('No shipped orders due for auto-completion.');
        }

        return self::SUCCESS;
    }
}
