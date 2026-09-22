<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Events\OrderStatusChanged;
use App\Jobs\SendOrderStatusEmail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OrderService — centralizes order lifecycle management. Order *creation*
 * lives in CheckoutService::createOrder() (the only live checkout path); this
 * class covers everything that happens to an order after it exists.
 *
 * Responsibilities:
 *  - Handle status transitions with validation
 *  - Log status changes to order_status_history
 *  - Generate invoice numbers
 *  - Recalculate order totals after an admin edits line items
 *
 * Status flow:
 *   pending       → paid, processing, shipped, delivered, cancelled
 *   paid          → processing, shipped, delivered, cancelled
 *   processing    → shipped, delivered, cancelled
 *   shipped       → delivered
 *   delivered     → refund_requested
 *   refund_requested → refunded
 *   refunded/cancelled → (terminal — no outgoing transitions)
 */
class OrderService
{
    public function __construct(
        private SequenceService $sequenceService,
    ) {}

    /**
     * Transition an order to a new status with validation.
     *
     * @param  bool  $notifyCustomer  Set false when the caller already sends its own,
     *                                more specific email for this exact transition.
     * @return bool True if transition was applied
     *
     * @throws \InvalidArgumentException if the transition is not allowed
     */
    public function transitionStatus(Order $order, OrderStatus $newStatus, ?string $note = null, ?int $adminId = null, bool $notifyCustomer = true): bool
    {
        $oldStatus = $order->status;

        if (! $this->isTransitionAllowed($oldStatus, $newStatus)) {
            throw new \InvalidArgumentException(
                "Status transition from {$oldStatus->value} to {$newStatus->value} is not allowed."
            );
        }

        $result = DB::transaction(function () use ($order, $oldStatus, $newStatus, $note, $adminId, $notifyCustomer) {
            $order->update(['status' => $newStatus]);

            $this->logStatusChange($order, $oldStatus, $newStatus, $note, $adminId);

            OrderStatusChanged::dispatch($order, $oldStatus, $newStatus);

            if ($notifyCustomer) {
                // dispatch() runs synchronously on the 'sync' queue connection
                // (local dev, and some shared-hosting installs per rule #41),
                // so a real SMTP failure throws right here — and since this
                // whole method runs inside DB::transaction(), an unguarded
                // throw would roll back the status transition itself just
                // because the notification email failed to send. Confirmed
                // live (real refund-request submission against a broken local
                // SMTP config threw the exact same class of error elsewhere
                // in this flow). The status change must persist regardless.
                try {
                    SendOrderStatusEmail::dispatch($order, $oldStatus, $newStatus);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // Auto-generate invoice number when order becomes paid
            if ($newStatus === OrderStatus::Paid && ! $order->invoice_number) {
                $order->update([
                    'invoice_number' => $this->sequenceService->nextInvoiceNumber(),
                ]);
            }

            return true;
        });

        // Runs after the transaction commits — an outbound HTTP call to
        // Airwallex has no business holding a DB transaction/row locks open.
        // Never blocks or throws back to the caller: the order has already
        // physically shipped either way, so a capture failure here needs an
        // admin's attention (logged), not a rolled-back shipment.
        if ($result && $newStatus === OrderStatus::Shipped) {
            $this->captureAuthorizedAirwallexPaymentIfAny($order);
        }

        return $result;
    }

    /**
     * The whole point of authorize-now/capture-later: hold funds while an
     * order might still get cancelled or turn out unfulfillable, only
     * actually take the money once it's guaranteed to go out the door. Every
     * order-shipped transition (admin "Change Status", the Awaiting
     * Confirmation widget, bulk actions — all of them funnel through
     * transitionStatus()) checks for a still-held Airwallex payment here.
     * A no-op for orders paid via auto-capture (nothing Authorized to find)
     * or any other gateway.
     */
    private function captureAuthorizedAirwallexPaymentIfAny(Order $order): void
    {
        $payment = $order->payments()
            ->where('gateway', PaymentGateway::Airwallex)
            ->where('status', PaymentTransactionStatus::Authorized)
            ->latest()
            ->first();

        if (! $payment) {
            return;
        }

        try {
            app(PaymentService::class)->captureAirwallexPayment($payment);
        } catch (\Throwable $e) {
            Log::error('Auto-capture on ship failed — payment remains authorized, needs manual capture', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * Mark payment as received and update order status.
     */
    public function markPaymentReceived(Order $order, string $paymentReference, string $paymentMethod = 'card'): void
    {
        $paymentReference = Str::limit(trim($paymentReference), 100);

        // lockForUpdate() only takes effect inside an open transaction —
        // called bare it acquires no lock at all, defeating the point of
        // re-fetching the row here.
        DB::transaction(function () use ($order, $paymentReference, $paymentMethod) {
            $order = Order::where('id', $order->id)->lockForUpdate()->first();

            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'payment_reference' => $paymentReference,
                'payment_method' => match ($paymentMethod) {
                    'card' => PaymentMethod::Card,
                    'paysera' => PaymentMethod::Paysera,
                    default => PaymentMethod::BankTransfer,
                },
            ]);

            if ($order->status === OrderStatus::Pending) {
                $this->transitionStatus($order, OrderStatus::Paid, 'Payment received');
            }
        });
    }

    /**
     * Mark payment as failed.
     */
    public function markPaymentFailed(Order $order, ?string $reference = null): void
    {
        try {
            $order->update([
                'payment_status' => PaymentStatus::Failed,
                'payment_reference' => $reference ?? $order->payment_reference,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark payment as failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cancel an order if allowed.
     */
    public function cancelOrder(Order $order, ?string $note = null, ?int $adminId = null): bool
    {
        if ($note !== null && mb_strlen($note) > 1000) {
            throw new \InvalidArgumentException('Cancellation reason must not exceed 1000 characters.');
        }

        return $this->transitionStatus($order, OrderStatus::Cancelled, $note, $adminId);
    }

    /**
     * Check if a status transition is valid per the defined flow.
     */
    public function isTransitionAllowed(OrderStatus $oldStatus, OrderStatus $newStatus): bool
    {
        $allowed = [
            OrderStatus::Pending->value => [
                OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped,
                OrderStatus::Delivered, OrderStatus::Cancelled,
            ],
            OrderStatus::Paid->value => [
                OrderStatus::Processing, OrderStatus::Shipped,
                OrderStatus::Delivered, OrderStatus::Cancelled,
            ],
            OrderStatus::Processing->value => [
                OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Cancelled,
            ],
            OrderStatus::Shipped->value => [OrderStatus::Delivered],
            OrderStatus::Delivered->value => [OrderStatus::RefundRequested],
            OrderStatus::RefundRequested->value => [OrderStatus::Refunded],
            OrderStatus::Refunded->value => [],
            OrderStatus::Cancelled->value => [],
        ];

        return in_array($newStatus, $allowed[$oldStatus->value] ?? [], true);
    }

    /**
     * Recalculate an order's money totals from its line items after an admin
     * edits them. Shipping, VAT, rush-processing fee, handling fee, and
     * discount are kept as stored — only subtotal and grand_total are
     * re-derived, using the same formula as CheckoutService's order
     * creation: grand = (subtotal + shipping + urgent fee + handling fee +
     * vat) − discount, floored at 0.00. Omitting either fee here would
     * silently drop it from the total the moment an admin edits a line item.
     */
    public function recalculateTotals(Order $order): void
    {
        $subtotal = '0.00';
        foreach ($order->items()->get(['total_price']) as $item) {
            $subtotal = bcadd($subtotal, (string) $item->total_price, 2);
        }

        $taxableBase = bcadd(bcadd(bcadd($subtotal, (string) $order->shipping_cost, 2), (string) $order->urgent_processing_fee, 2), (string) $order->handling_fee, 2);
        $grandTotal = bcsub(bcadd($taxableBase, (string) $order->vat_amount, 2), (string) $order->discount_amount, 2);

        if (bccomp($grandTotal, '0.00', 2) === -1) {
            $grandTotal = '0.00';
        }

        $order->forceFill([
            'subtotal' => $subtotal,
            'grand_total' => $grandTotal,
        ])->save();
    }

    /**
     * Log a status change to the order_status_history table.
     */
    private function logStatusChange(Order $order, ?OrderStatus $oldStatus, OrderStatus $newStatus, ?string $note = null, ?int $adminId = null): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'admin_id' => $adminId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
        ]);
    }
}
