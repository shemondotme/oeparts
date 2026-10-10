<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Services\CancelledOrderSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancelling an order that already carries the customer's money must never
 * leave that money silently behind — see CancelledOrderSettlement for what
 * happens. Runs after the surrounding transaction commits: releasing a card
 * hold is an outbound HTTP call that has no business holding the
 * status-change transaction open, and the alert must describe committed state.
 */
class SettleCancelledPaidOrder
{
    public function __construct(private CancelledOrderSettlement $settlement) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->newStatus !== OrderStatus::Cancelled) {
            return;
        }

        $order = $event->order;

        DB::afterCommit(function () use ($order): void {
            try {
                $this->settlement->settle($order->fresh() ?? $order);
            } catch (\Throwable $e) {
                // Must never break the cancellation itself.
                Log::error('Settling a cancelled paid order failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
