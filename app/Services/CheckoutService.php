<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * CheckoutService — orchestrates the 5‑step checkout flow.
 *
 * Steps:
 *   1. Guest email + OTP verification (OtpService)
 *   2. B2B VAT validation (ViesService)
 *   3. Shipping address
 *   4. Shipping method selection
 *   5. Review & place order (creates Order, empties Cart)
 *
 * All checkout state is stored in the session under the key `checkout`.
 * The session data is validated and transformed into an Order at step 5.
 */
class CheckoutService
{
    public function __construct(
        private OtpService $otpService,
        private ViesService $viesService,
        private SequenceService $sequenceService,
        private SettingsService $settings,
        private CartService $cartService
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
                'vat_number' => null,
                'vat_exempt' => false,
                'company_name' => null,
                'is_b2b' => false,
                'shipping_address' => null,
                'shipping_method_id' => null,
                'payment_method' => 'card',
                'customer_note' => null,
                'urgent_processing' => false,
            ],
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(
                $this->settings->get('checkout.timeout_minutes', 30)
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

        $checkout['step'] = min(5, $currentStep + 1);
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
                // Testing bypass — see CHECKOUT_SKIP_OTP in .env.
                $skipOtp = (bool) config('app.checkout_skip_otp');
                return !empty($data['contact_email'])
                    && ($skipOtp || $data['otp_verified'] === true || auth()->check());
            case 2:
                if (empty($data['shipping_address'])) {
                    return false;
                }

                if ($data['is_b2b'] ?? false) {
                    return !empty($data['company_name']) && !empty($data['vat_number']);
                }

                return true;
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

        foreach (['step', 'expires_at', 'created_at', 'cart_id'] as $topLevelKey) {
            if (array_key_exists($topLevelKey, $updates)) {
                $checkout[$topLevelKey] = $updates[$topLevelKey];
                unset($updates[$topLevelKey]);
            }
        }

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
     * Validate VAT number using VIES and update session.
     */
    public function validateVat(string $checkoutId, string $vatNumber, ?string $countryCode = null): array
    {
        $result = $this->viesService->validate($vatNumber, $countryCode);

        $this->update($checkoutId, [
            'vat_number' => $vatNumber,
            'vat_exempt' => $result['valid'] ?? false,
            'company_name' => $result['name'] ?? null,
            'is_b2b' => true,
        ]);

        return $result;
    }

    /**
     * Create the final order from the checkout session.
     * Returns the Order model on success, throws on failure.
     */
    public function createOrder(string $checkoutId): Order
    {
        return DB::transaction(function () use ($checkoutId) {
            $checkout = $this->get($checkoutId);
            if (!$checkout) {
                throw new \RuntimeException('Checkout session expired or not found.');
            }

            $cart = Cart::find($checkout['cart_id']);
            if (!$cart || $cart->items->isEmpty()) {
                throw new \RuntimeException('Cart is empty or invalid.');
            }

            $data = $checkout['data'];
            $cart->loadMissing('items.product.manufacturer');

            // Calculate totals using CartService
            $cartSummary = $this->cartService->getSummary($cart);
            $subtotal = number_format((float) $cartSummary['subtotal'], 2, '.', '');
            $shippingCost = $this->calculateShippingCost($cart, $data['shipping_method_id']);
            $taxableBase = bcadd((string) $subtotal, (string) $shippingCost, 2);
            $vatAmount = $data['vat_exempt'] ? '0.00' : $this->calculateVat($taxableBase);
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
            $shippingMethod = !empty($data['shipping_method_id'])
                ? ShippingMethod::find($data['shipping_method_id'])
                : null;
            $shippingName = trim(implode(' ', array_filter([
                $shippingAddress['first_name'] ?? null,
                $shippingAddress['last_name'] ?? null,
            ])));

            // Create order
            $order = Order::create([
                'order_number' => $this->sequenceService->nextOrderNumber(),
                'user_id' => auth()->id(),
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
                'shipping_method_name_snapshot' => $shippingMethod ? trans_field($shippingMethod->name) : null,
                'shipping_estimated_days_min' => $shippingMethod?->estimated_days_min,
                'shipping_estimated_days_max' => $shippingMethod?->estimated_days_max,
                'shipping_name' => $shippingName,
                'shipping_address_line1' => $shippingAddress['street'] ?? null,
                'shipping_city' => $shippingAddress['city'] ?? null,
                'shipping_postal_code' => $shippingAddress['postal_code'] ?? null,
                'shipping_country_code' => $shippingAddress['country_code'] ?? null,
                'is_b2b' => $data['is_b2b'] ?? false,
                'company_name' => $data['company_name'],
                'vat_number' => $data['vat_number'],
                'vat_exempt' => $data['vat_exempt'] ?? false,
                'customer_note' => $data['customer_note'],
                'urgent_processing' => $data['urgent_processing'] ?? false,
                'ip_address' => request()->ip(),
                // UTM parameters from session
                'utm_source' => session('utm_source'),
                'utm_medium' => session('utm_medium'),
                'utm_campaign' => session('utm_campaign'),
                'utm_content' => session('utm_content'),
            ]);

            // Create order items
            foreach ($cart->items as $item) {
                $product = $item->product;

                // Manufacturer name is stored as a multilingual JSON array on the
                // Manufacturer model — snapshot the current locale's value.
                $manufacturerSnapshot = $product && $product->manufacturer
                    ? (trans_field($product->manufacturer->name) ?: 'Unknown')
                    : 'Unknown';

                // Condition is a BackedEnum cast on Product; materialise as scalar.
                $conditionSnapshot = $product && $product->condition instanceof \BackedEnum
                    ? $product->condition->value
                    : (string) ($product->condition ?? '');

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
            if ($data['guest_email'] && !auth()->check()) {
                $this->createGuestAccount($data['guest_email'], $order);
            }

            return $order;
        });
    }

    /**
     * Calculate shipping cost based on selected method.
     */
    public function calculateShippingCost(Cart $cart, ?int $shippingMethodId): string
    {
        if (!$shippingMethodId) {
            return '0.00';
        }

        $shippingMethod = ShippingMethod::find($shippingMethodId);
        if (!$shippingMethod || !$shippingMethod->is_active) {
            return '0.00';
        }

        $subtotal = '0.00';
        foreach ($cart->items as $item) {
            $subtotal = bcadd(
                $subtotal,
                bcmul((string) $item->price_at_add, (string) $item->quantity, 2),
                2
            );
        }

        $freeShippingThreshold = $shippingMethod->free_shipping_threshold !== null
            ? (string) $shippingMethod->free_shipping_threshold
            : (string) settings('shipping.free_threshold', 0);

        if (
            bccomp($freeShippingThreshold, '0.00', 2) === 1
            && bccomp($subtotal, $freeShippingThreshold, 2) >= 0
        ) {
            return '0.00';
        }

        return number_format((float) $shippingMethod->flat_rate, 2, '.', '');
    }

    /**
     * Calculate VAT amount based on customer's country and B2B status.
     */
    private function calculateVat(string $amount): string
    {
        // TODO: implement VAT calculation based on shipping country
        $vatRate = (string) settings('tax.default_vat_rate', 21);
        return bcmul($amount, bcdiv($vatRate, '100', 4), 2);
    }

    /**
     * Automatically create a user account for a guest after order placement.
     */
    private function createGuestAccount(string $email, Order $order): void
    {
        // Check if a user with this email already exists
        $user = User::where('email', $email)->first();
        if ($user) {
            // Link order to existing user
            $order->update(['user_id' => $user->id]);
            return;
        }

        // Create new user with a random password
        $password = Str::random(12);
        $user = User::create([
            'name' => 'Guest ' . explode('@', $email)[0], // Generate name from email
            'email' => $email,
            'password' => bcrypt($password),
            'email_verified_at' => now(),
        ]);

        // Send welcome email with password set link (implement in EmailService)
        // ...

        // Link order to the new user
        $order->update(['user_id' => $user->id]);
    }
}