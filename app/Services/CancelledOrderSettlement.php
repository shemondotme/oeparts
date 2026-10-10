<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Notifications\PaidOrderCancelledNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Makes sure the customer's money is dealt with once an order is cancelled:
 *
 *  - a card HOLD (Airwallex authorized, never captured) is released at once,
 *    so the customer is not left with funds frozen on their card;
 *  - money that has actually been TAKEN (captured card payment, received bank
 *    transfer) is queued as a tracked refund in the Refunds list — refunds
 *    here are returned by staff and then marked processed, which also sends
 *    the customer's "refund processed" email;
 *  - staff are told which of the two happened, and what (if anything) they
 *    still have to do.
 *
 * Called when an order is cancelled (SettleCancelledPaidOrder) AND when a
 * payment confirmation arrives for an order that is already cancelled — an
 * expired or customer-cancelled order the customer then paid anyway.
 * Idempotent: a second call never queues a second refund.
 */
class CancelledOrderSettlement
{
    public function __construct(private PaymentService $payments) {}

    public function settle(Order $order): void
    {
        /** @var Collection<int, Payment> $held */
        $held = $order->payments()
            ->where('gateway', PaymentGateway::Airwallex)
            ->where('status', PaymentTransactionStatus::Authorized)
            ->get();

        $taken = $order->payment_status === PaymentStatus::Paid
            || $order->payments()->where('status', PaymentTransactionStatus::Captured)->exists();

        if ($held->isEmpty() && ! $taken) {
            return;
        }

        $releaseFailed = false;
        foreach ($held as $payment) {
            try {
                $this->payments->releaseAirwallexAuthorization($payment);
            } catch (\Throwable $e) {
                $releaseFailed = true;
                Log::error('Could not release the card hold of a cancelled order', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($taken) {
            $this->queueRefund($order);
        }

        $outcome = match (true) {
            $taken => PaidOrderCancelledNotification::REFUND_DUE,
            $releaseFailed => PaidOrderCancelledNotification::HOLD_RELEASE_FAILED,
            default => PaidOrderCancelledNotification::HOLD_RELEASED,
        };

        Log::warning('Paid order cancelled', ['order_id' => $order->id, 'outcome' => $outcome]);

        foreach (Admin::where('is_active', true)->get() as $admin) {
            try {
                $admin->notify(new PaidOrderCancelledNotification($order, $outcome));
            } catch (\Throwable $e) {
                Log::error('Failed to notify an admin of a paid order cancellation', [
                    'order_id' => $order->id,
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * One tracked refund per cancelled order. Needs a customer account (the
     * refund table is keyed on one — guest checkout creates it); an order
     * without one is covered by the staff alert alone.
     */
    private function queueRefund(Order $order): void
    {
        if (! $order->user_id) {
            return;
        }

        $alreadyTracked = RefundRequest::where('order_id', $order->id)
            ->whereIn('status', [RefundStatus::Pending, RefundStatus::Approved, RefundStatus::Processed])
            ->exists();

        if ($alreadyTracked) {
            return;
        }

        RefundRequest::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'reason' => 'Order cancelled after payment — the customer is owed a full refund.',
            'amount_requested' => $order->grand_total,
            'status' => RefundStatus::Approved,
            'admin_note' => 'Created automatically when the order was cancelled. Return the money, then press "Mark as processed".',
        ]);
    }
}
