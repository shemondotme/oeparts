<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendOrderStatusEmail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OrderService — centralizes order lifecycle management.
 *
 * Responsibilities:
 *  - Create orders from checkout data
 *  - Handle status transitions with validation
 *  - Log status changes to order_status_history
 *  - Generate invoice numbers
 *  - Calculate order totals using bcmath
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
        private SettingsService $settings,
        private CartService $cartService
    ) {}

    /**
     * Create an order from checkout data.
     *
     * @param array $checkoutData  Full checkout data array
     * @param Cart $cart           The validated cart with items loaded
     * @param int|null $userId    Explicit user ID — null for guest checkout
     * @param string|null $ipAddress Client IP — null uses request()->ip()
     * @param array|null $utmParams  UTM tracking params — null reads from session
     * @return Order
     *
     * @throws \RuntimeException on validation failure
     */
    public function createFromCheckout(
        array $checkoutData,
        Cart $cart,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?array $utmParams = null,
    ): Order
    {
        $data = $checkoutData['data'] ?? $checkoutData;

        $requiredKeys = ['shipping_method_id'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException("Missing required checkout data key: {$key}");
            }
        }

        return DB::transaction(function () use ($checkoutData, $cart, $userId, $ipAddress, $utmParams) {
            $data = $checkoutData['data'] ?? $checkoutData;
            $cart->loadMissing('items.product.manufacturer');

            // Calculate totals
            $cartSummary = $this->cartService->getSummary($cart);
            $subtotal = bcadd((string) $cartSummary['subtotal'], '0', 2);
            $shippingCost = $this->calculateShippingCost($cart, $data['shipping_method_id'] ?? null);
            $taxableBase = bcadd($subtotal, $shippingCost, 2);
            $vatAmount = ($data['vat_exempt'] ?? false) ? '0.00' : $this->calculateVat($taxableBase);
            $discountAmount = $data['discount_amount'] ?? '0.00';
            $grandTotal = bcsub(bcadd($taxableBase, $vatAmount, 2), $discountAmount, 2);

            if (bccomp($grandTotal, '0.00', 2) === -1) {
                $grandTotal = '0.00';
            }

            // Resolve payment method enum
            $paymentMethod = match($data['payment_method'] ?? 'bank_transfer') {
                'card'          => \App\Enums\PaymentMethod::Card,
                'bank_transfer' => \App\Enums\PaymentMethod::BankTransfer,
                default         => \App\Enums\PaymentMethod::BankTransfer,
            };

            $shippingAddress = $data['shipping_address'] ?? [];
            $shippingName = trim(implode(' ', array_filter([
                $shippingAddress['first_name'] ?? '',
                $shippingAddress['last_name'] ?? '',
            ])));

            // Create the order record
            $resolvedUserId = $userId ?? auth()->id();
            $resolvedIp = $ipAddress ?? request()->ip();
            $utm = $utmParams ?? [
                'source'   => session('utm_source'),
                'medium'   => session('utm_medium'),
                'campaign' => session('utm_campaign'),
                'content'  => session('utm_content'),
            ];

            $order = Order::create([
                'order_number'                 => $this->sequenceService->nextOrderNumber(),
                'user_id'                      => $resolvedUserId,
                'guest_email'                  => $data['guest_email'] ?? null,
                'status'                       => OrderStatus::Pending,
                'payment_method'               => $paymentMethod,
                'payment_status'               => PaymentStatus::Pending,
                'subtotal'                     => $subtotal,
                'shipping_cost'                => $shippingCost,
                'vat_amount'                   => $vatAmount,
                'grand_total'                  => $grandTotal,
                'coupon_id'                    => $data['coupon_id'] ?? null,
                'discount_amount'              => $discountAmount,
                'shipping_method_id'           => $data['shipping_method_id'] ?? null,
                'shipping_method_name_snapshot' => $data['shipping_method_name'] ?? null,
                'shipping_estimated_days_min'  => $data['shipping_estimated_days_min'] ?? null,
                'shipping_estimated_days_max'  => $data['shipping_estimated_days_max'] ?? null,
                'shipping_name'                => $shippingName,
                'shipping_address_line1'       => $shippingAddress['street'] ?? $shippingAddress['address_line1'] ?? null,
                'shipping_city'                => $shippingAddress['city'] ?? null,
                'shipping_postal_code'         => $shippingAddress['postal_code'] ?? null,
                'shipping_country_code'        => $shippingAddress['country_code'] ?? null,
                'company_name'                 => $data['company_name'] ?? null,
                'vat_number'                   => $data['vat_number'] ?? null,
                'vat_exempt'                   => $data['vat_exempt'] ?? false,
                'is_b2b'                       => $data['is_b2b'] ?? !empty($data['vat_number']),
                'customer_note'                => $data['customer_note'] ?? null,
                'ip_address'                   => $resolvedIp,
                'utm_source'                   => $utm['source'] ?? null,
                'utm_medium'                   => $utm['medium'] ?? null,
                'utm_campaign'                 => $utm['campaign'] ?? null,
                'utm_content'                  => $utm['content'] ?? null,
            ]);

            // Create order items from cart items
            foreach ($cart->items as $item) {
                $product = $item->product;
                $manufacturerSnapshot = $product && $product->manufacturer
                    ? (trans_field($product->manufacturer->name) ?: 'Unknown')
                    : 'Unknown';

                $conditionSnapshot = $product?->condition?->slug ?? '';

                OrderItem::create([
                    'order_id'             => $order->id,
                    'product_id'           => $item->product_id,
                    'oem_number_snapshot'  => $product->oem_number ?? '',
                    'manufacturer_snapshot' => $manufacturerSnapshot,
                    'condition_snapshot'   => $conditionSnapshot,
                    'quantity'             => $item->quantity,
                    'unit_price'           => $item->price_at_add,
                    'total_price'          => bcmul((string) $item->price_at_add, (string) $item->quantity, 2),
                ]);
            }

            // Log initial status
            $this->logStatusChange($order, null, OrderStatus::Pending);

            return $order;
        });
    }

    /**
     * Transition an order to a new status with validation.
     *
     * @param Order $order
     * @param OrderStatus $newStatus
     * @param string|null $note
     * @param int|null $adminId
     * @param bool $notifyCustomer  Set false when the caller already sends its own,
     *                              more specific email for this exact transition.
     * @return bool  True if transition was applied
     *
     * @throws \InvalidArgumentException if the transition is not allowed
     */
    public function transitionStatus(Order $order, OrderStatus $newStatus, ?string $note = null, ?int $adminId = null, bool $notifyCustomer = true): bool
    {
        $oldStatus = $order->status;

        if (!$this->isTransitionAllowed($oldStatus, $newStatus)) {
            throw new \InvalidArgumentException(
                "Status transition from {$oldStatus->value} to {$newStatus->value} is not allowed."
            );
        }

        return DB::transaction(function () use ($order, $oldStatus, $newStatus, $note, $adminId, $notifyCustomer) {
            $order->update(['status' => $newStatus]);

            $this->logStatusChange($order, $oldStatus, $newStatus, $note, $adminId);

            \App\Events\OrderStatusChanged::dispatch($order, $oldStatus, $newStatus);

            if ($notifyCustomer) {
                SendOrderStatusEmail::dispatch($order, $oldStatus, $newStatus);
            }

            // Auto-generate invoice number when order becomes paid
            if ($newStatus === OrderStatus::Paid && !$order->invoice_number) {
                $order->update([
                    'invoice_number' => $this->sequenceService->nextInvoiceNumber(),
                ]);
            }

            return true;
        });
    }

    /**
     * Mark payment as received and update order status.
     */
    public function markPaymentReceived(Order $order, string $paymentReference, string $paymentMethod = 'card'): void
    {
        $paymentReference = Str::limit(trim($paymentReference), 100);

        $order = Order::where('id', $order->id)->lockForUpdate()->first();

        $order->update([
            'payment_status'     => PaymentStatus::Paid,
            'payment_reference'  => $paymentReference,
            'payment_method'     => $paymentMethod === 'card'
                ? \App\Enums\PaymentMethod::Card
                : \App\Enums\PaymentMethod::BankTransfer,
        ]);

        if ($order->status === OrderStatus::Pending) {
            $this->transitionStatus($order, OrderStatus::Paid, 'Payment received');
        }
    }

    /**
     * Mark payment as failed.
     */
    public function markPaymentFailed(Order $order, string $reference = null): void
    {
        try {
            $order->update([
                'payment_status'    => PaymentStatus::Failed,
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
            OrderStatus::Pending->value  => [
                OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped,
                OrderStatus::Delivered, OrderStatus::Cancelled,
            ],
            OrderStatus::Paid->value     => [
                OrderStatus::Processing, OrderStatus::Shipped,
                OrderStatus::Delivered, OrderStatus::Cancelled,
            ],
            OrderStatus::Processing->value => [
                OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Cancelled,
            ],
            OrderStatus::Shipped->value  => [OrderStatus::Delivered],
            OrderStatus::Delivered->value => [OrderStatus::RefundRequested],
            OrderStatus::RefundRequested->value => [OrderStatus::Refunded],
            OrderStatus::Refunded->value => [],
            OrderStatus::Cancelled->value => [],
        ];

        return in_array($newStatus, $allowed[$oldStatus->value] ?? [], true);
    }

    /**
     * Calculate shipping cost. Returns '0.00' if no method selected.
     */
    public function calculateShippingCost(Cart $cart, ?int $shippingMethodId): string
    {
        if (!$shippingMethodId) {
            return '0.00';
        }

        $method = \App\Models\ShippingMethod::find($shippingMethodId);
        if (!$method || !$method->is_active) {
            return '0.00';
        }

        // Check free shipping threshold
        $cartSummary = $this->cartService->getSummary($cart);
        $subtotal = $cartSummary['subtotal'] ?? '0.00';

        if ($method->free_shipping_threshold !== null
            && bccomp((string) $subtotal, (string) $method->free_shipping_threshold, 2) >= 0) {
            return '0.00';
        }

        return (string) $method->flat_rate;
    }

    /**
     * Calculate VAT amount based on configured rate.
     */
    public function calculateVat(string $amount): string
    {
        $vatRate = (string) $this->settings->get('tax.default_vat_rate', 21);
        return bcmul($amount, bcdiv($vatRate, '100', 4), 2);
    }

    /**
     * Log a status change to the order_status_history table.
     */
    private function logStatusChange(Order $order, ?OrderStatus $oldStatus, OrderStatus $newStatus, ?string $note = null, ?int $adminId = null): void
    {
        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'admin_id'   => $adminId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note'       => $note,
        ]);
    }
}