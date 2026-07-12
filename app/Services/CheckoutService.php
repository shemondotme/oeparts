<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * CheckoutService — orchestrates the 5‑step checkout flow.
 *
 * Steps:
 *   1. Contact email + phone
 *   2. Shipping address
 *   3. Shipping method selection
 *   4. Review & accept terms
 *   5. Payment method & place order (creates Order, empties Cart)
 *
 * All checkout state is stored in the session under the key `checkout`.
 * The session data is validated and transformed into an Order at step 5.
 */
class CheckoutService
{
    public function __construct(
        private SequenceService $sequenceService,
        private SettingsService $settings,
        private CartService $cartService,
        private ShippingService $shippingService
    ) {}

    /**
     * Start a new checkout session for the given cart.
     * Returns the session key for the checkout.
     */
    public function start(Cart $cart): string
    {
        $checkoutId = Str::uuid()->toString();

        Session::put("checkout.{$checkoutId}", [
            'cart_id' => $cart->id,
            'step' => 1,
            'data' => [
                'contact_email' => null,
                'contact_phone' => null,
                'guest_email' => null,
                'otp_verified' => false,
                'shipping_address' => null,
                'shipping_method_id' => null,
                'payment_method' => settings('checkout.default_payment_method', 'card'),
                'customer_note' => null,
                'urgent_processing' => false,
            ],
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(
                (int) $this->settings->get('checkout.timeout_minutes', 30)
            )->toIso8601String(),
        ]);

        return $checkoutId;
    }

    /**
     * Advance to the next step if the current step is complete.
     */
    public function advance(string $checkoutId): bool
    {
        $checkout = $this->get($checkoutId);
        if (!$checkout) {
            return false;
        }

        $currentStep = $checkout['step'];
        if (!$this->isStepComplete($checkoutId, $currentStep)) {
            return false;
        }

        $checkout['step'] = min((int) settings('checkout.max_steps', 5), $currentStep + 1);
        Session::put("checkout.{$checkoutId}", $checkout);
        return true;
    }

    /**
     * Move back one step, never below step 1.
     */
    public function goBack(string $checkoutId): bool
    {
        $checkout = $this->get($checkoutId);
        if (!$checkout) {
            return false;
        }

        $checkout['step'] = max(1, ((int) $checkout['step']) - 1);
        Session::put("checkout.{$checkoutId}", $checkout);

        return true;
    }

    /**
     * Check whether a specific step is complete.
     */
    public function isStepComplete(string $checkoutId, int $step): bool
    {
        $checkout = $this->get($checkoutId);
        if (!$checkout) {
            return false;
        }

        $data = $checkout['data'];

        switch ($step) {
            case 1:
                return !empty($data['contact_email']);
            case 2:
                return !empty($data['shipping_address']);
            case 3:
                return !empty($data['shipping_method_id']);
            case 4:
                return $this->isStepComplete($checkoutId, 1)
                    && $this->isStepComplete($checkoutId, 2)
                    && $this->isStepComplete($checkoutId, 3);
            case 5:
                return $this->isStepComplete($checkoutId, 1)
                    && $this->isStepComplete($checkoutId, 2)
                    && $this->isStepComplete($checkoutId, 3)
                    && $this->isStepComplete($checkoutId, 4);
            default:
                return false;
        }
    }

    /**
     * Retrieve the checkout session data.
     */
    public function get(string $checkoutId): ?array
    {
        $data = Session::get("checkout.{$checkoutId}");
        if (!$data) {
            return null;
        }

        // Check expiration
        $expiresAt = Carbon::parse($data['expires_at']);
        if ($expiresAt->isPast()) {
            $this->clear($checkoutId);
            return null;
        }

        return $data;
    }

    /**
     * Update data for the current step.
     */
    public function update(string $checkoutId, array $updates): bool
    {
        $checkout = $this->get($checkoutId);
        if (!$checkout) {
            return false;
        }

        $allowed = [
            'step', 'shipping_address', 'billing_address', 'shipping_method_id', 'payment_method', 'notes',
            'vat_number', 'vat_valid', 'vat_exempt', 'coupon_code', 'company_name',
            'contact_email', 'contact_phone', 'guest_email', 'otp_verified', 'is_b2b', 'customer_note',
            'urgent_processing'
        ];

        foreach (['step', 'expires_at', 'created_at', 'cart_id'] as $topLevelKey) {
            if (array_key_exists($topLevelKey, $updates)) {
                $checkout[$topLevelKey] = $updates[$topLevelKey];
                unset($updates[$topLevelKey]);
            }
        }

        $updates = array_intersect_key($updates, array_flip($allowed));

        $checkout['data'] = array_merge($checkout['data'], $updates);
        Session::put("checkout.{$checkoutId}", $checkout);
        return true;
    }

    /**
     * Clear the checkout session.
     */
    public function clear(string $checkoutId): void
    {
        Session::forget("checkout.{$checkoutId}");
    }

    /**
     * Return a shipping method from the database by ID, or null.
     */
    private function getShippingMethod(?int $id): ?object
    {
        if (! $id) {
            return null;
        }

        $method = \App\Models\ShippingMethod::where('is_active', true)->find($id);

        if (! $method) {
            return null;
        }

        $name = is_array($method->name) ? ($method->name['en'] ?? reset($method->name)) : $method->name;

        return (object) [
            'id' => $method->id,
            'name' => $name,
            'flat_rate' => $method->flat_rate,
            'estimated_days_min' => $method->estimated_days_min,
            'estimated_days_max' => $method->estimated_days_max,
        ];
    }

    /**
     * Create the final order from the checkout session.
     * Returns the Order model on success, throws on failure.
     *
     * @param string $checkoutId
     * @param int|null $userId       Explicit user ID — null for guest checkout
     * @param string|null $ipAddress Client IP — null uses request()->ip()
     * @param array $utmParams       UTM tracking params — null reads from session
     */
    public function createOrder(
        string $checkoutId,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?array $utmParams = null,
    ): Order {
        return DB::transaction(function () use ($checkoutId, $userId, $ipAddress, $utmParams) {
            $checkout = $this->get($checkoutId);
            if (!$checkout) {
                throw new \RuntimeException('Checkout session expired or not found.');
            }

            $cart = Cart::find($checkout['cart_id']);
            if (!$cart || $cart->items->isEmpty()) {
                throw new \RuntimeException('Cart is empty or invalid.');
            }

            $data = $checkout['data'];
            $cart->loadMissing('items.product.manufacturer', 'items.product.condition');

            // Calculate totals using CartService
            $cartSummary = $this->cartService->getSummary($cart);
            $subtotal = bcadd((string) $cartSummary['subtotal'], '0', 2);
            $shippingCost = $this->calculateShippingCost($cart, $data['shipping_method_id']);

            // Rush-processing upsell: re-check the merchant toggle at charge
            // time, not just the session flag — a customer's session could
            // predate an operator disabling the feature mid-checkout.
            $urgentProcessing = (bool) ($data['urgent_processing'] ?? false) && (bool) settings('checkout.urgent_processing_enabled', false);
            $urgentProcessingFee = $urgentProcessing ? bcadd((string) settings('checkout.urgent_processing_fee', '0.00'), '0', 2) : '0.00';
            $handlingFee = bcadd((string) settings('shipping.handling_fee', '0.00'), '0', 2);

            $taxableBase = bcadd(bcadd(bcadd((string) $subtotal, (string) $shippingCost, 2), $urgentProcessingFee, 2), $handlingFee, 2);
            $vatAmount = ($data['vat_exempt'] ?? false) ? '0.00' : $this->calculateVat($taxableBase, $data);
            $grandTotal = bcadd($taxableBase, $vatAmount, 2);

            // --- Coupon application ---
            $couponId      = $data['coupon_id'] ?? null;
            $discountAmount = $data['discount_amount'] ?? '0.00';
            $coupon = null;

            if ($couponId) {
                $coupon = \App\Models\Coupon::find($couponId);
                if (!$coupon) {
                    $couponId      = null;
                    $discountAmount = '0.00';
                }
            }

            // Recalculate grand total with discount
            $grandTotal = bcsub(bcadd($taxableBase, $vatAmount, 2), $discountAmount, 2);
            // Floor at 0.00 (can't be negative)
            if (bccomp($grandTotal, '0.00', 2) === -1) {
                $grandTotal = '0.00';
            }

            // Determine payment method from session
            $paymentMethod = PaymentMethod::BankTransfer; // default
            if (isset($data['payment_method'])) {
                $paymentMethod = match($data['payment_method']) {
                    'card' => PaymentMethod::Card,
                    'bank_transfer' => PaymentMethod::BankTransfer,
                    default => PaymentMethod::BankTransfer
                };
            }

            $shippingAddress = $data['shipping_address'] ?? [];
            $shippingMethod = $this->getShippingMethod($data['shipping_method_id'] ?? null);
            $shippingName = trim(implode(' ', array_filter([
                $shippingAddress['first_name'] ?? null,
                $shippingAddress['last_name'] ?? null,
            ])));

            // Resolve context: explicit params or session/request helpers
            $resolvedUserId = $userId ?? auth()->id();
            $resolvedIp = $ipAddress ?? request()->ip();
            $utm = $utmParams ?? [
                'source'   => session('utm_source'),
                'medium'   => session('utm_medium'),
                'campaign' => session('utm_campaign'),
                'content'  => session('utm_content'),
            ];

            // Create order
            $order = Order::create([
                'order_number' => $this->sequenceService->nextOrderNumber(),
                'user_id' => $resolvedUserId,
                'guest_email' => $data['guest_email'],
                'status' => OrderStatus::Pending,
                'payment_method' => $paymentMethod,
                'payment_status' => PaymentStatus::Pending,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'vat_amount' => $vatAmount,
                'grand_total' => $grandTotal,
                'coupon_id'       => $couponId,
                'discount_amount' => $discountAmount,
                'shipping_method_id' => $data['shipping_method_id'],
                'shipping_method_name_snapshot' => $shippingMethod?->name,
                'shipping_estimated_days_min' => $shippingMethod?->estimated_days_min,
                'shipping_estimated_days_max' => $shippingMethod?->estimated_days_max,
                'shipping_name' => $shippingName,
                'shipping_address_line1' => $shippingAddress['street'] ?? null,
                'shipping_city' => $shippingAddress['city'] ?? null,
                'shipping_postal_code' => $shippingAddress['postal_code'] ?? null,
                'shipping_country_code' => $shippingAddress['country_code'] ?? null,
                'customer_note' => $data['customer_note'],
                'urgent_processing' => $urgentProcessing,
                'urgent_processing_fee' => $urgentProcessingFee,
                'handling_fee' => $handlingFee,
                'ip_address' => $resolvedIp,
                'utm_source' => $utm['source'] ?? null,
                'utm_medium' => $utm['medium'] ?? null,
                'utm_campaign' => $utm['campaign'] ?? null,
                'utm_content' => $utm['content'] ?? null,
            ]);

            // Create order items
            foreach ($cart->items as $item) {
                $product = $item->product;

                // Manufacturer name is stored as a multilingual JSON array on the
                // Manufacturer model — snapshot the current locale's value.
                $manufacturerSnapshot = $product && $product->manufacturer
                    ? (trans_field($product->manufacturer->name) ?: 'Unknown')
                    : 'Unknown';

                $conditionSnapshot = $product?->condition?->slug ?? '';

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'oem_number_snapshot' => $product->oem_number ?? '',
                    'manufacturer_snapshot' => $manufacturerSnapshot,
                    'condition_snapshot' => $conditionSnapshot,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price_at_add,
                    'total_price' => bcmul((string) $item->price_at_add, (string) $item->quantity, 2),
                ]);
            }

            // Apply coupon usage if a coupon was used
            if ($coupon) {
                app(\App\Services\CouponService::class)->apply($coupon, $order);
            }

            // Clear cart
            $cart->items()->delete();
            $cart->delete();

            // Clear checkout session
            $this->clear($checkoutId);

            // Auto‑create guest account if guest_email is set and no user logged in
            if ($data['guest_email'] && !$resolvedUserId) {
                $this->createGuestAccount($data['guest_email'], $order);
            }

            dispatch(new GenerateInvoicePdf($order));

            \App\Events\OrderPlaced::dispatch($order);

            return $order;
        });
    }

    /**
     * Calculate shipping cost based on selected method from database.
     */
    public function calculateShippingCost(Cart $cart, ?int $shippingMethodId): string
    {
        if (! $shippingMethodId) {
            return '0.00';
        }

        return $this->shippingService->calculateCost($cart, $shippingMethodId);
    }

    /**
     * Calculate VAT amount based on customer's country and B2B status.
     */
    private function calculateVat(string $amount, array $data): string
    {
        // Check if customer is VAT exempt (B2B with valid VIES)
        if (!empty($data['vat_number']) && !empty($data['vat_valid']) && $data['vat_valid'] === true) {
            return '0.00';
        }

        $vatRate = (string) settings('tax.default_vat_rate', '21.00');
        return bcmul($amount, bcdiv($vatRate, '100', 4), 2);
    }

    /**
     * Automatically create a user account for a guest after order placement.
     */
    private function createGuestAccount(string $email, Order $order): \App\Models\User
    {
        return DB::transaction(function () use ($email, $order) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $order->update(['user_id' => $user->id]);
                return $user;
            }

            $password = Str::random((int) settings('checkout.guest_password_length', 12));
            $user = User::create([
                'name' => 'Guest ' . explode('@', $email)[0],
                'email' => $email,
                'password' => bcrypt($password),
                'email_verified_at' => now(),
            ]);

            $order->update(['user_id' => $user->id]);

            return $user;
        });
    }
}