<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Events\OrderStatusChanged;
use App\Models\Admin;
use App\Notifications\PaidOrderCancelledNotification;
use Illuminate\Support\Facades\Log;

/**
 * Cancelling a paid order must never leave the money silently behind.
 * "Paid" here means the customer's money is with us or held for us: the order's
 * payment_status is Paid, or a gateway payment is captured / authorized.
 */
class NotifyAdminOfPaidOrderCancelled
{
    public function handle(OrderStatusChanged $event): void
    {
        if ($event->newStatus !== OrderStatus::Cancelled) {
            return;
        }

        $order = $event->order;

        $holdsMoney = $order->payment_status === PaymentStatus::Paid
            || $order->payments()->whereIn('status', [
                PaymentTransactionStatus::Captured,
                PaymentTransactionStatus::Authorized,
            ])->exists();

        if (! $holdsMoney) {
            return;
        }

        Log::warning('Paid order cancelled — refund / hold release needed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => (string) $order->grand_total,
        ]);

        try {
            foreach (Admin::where('is_active', true)->get() as $admin) {
                $admin->notify(new PaidOrderCancelledNotification($order));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to notify admins of a paid order cancellation', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
