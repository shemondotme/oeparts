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

        $due = Order::query()
            ->where('status', OrderStatus::Shipped)
            ->whereIn('id', OrderStatusHistory::query()
                ->select('order_id')
                ->where('new_status', OrderStatus::Shipped->value)
                ->where('created_at', '<=', now()->subDays($days)))
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
