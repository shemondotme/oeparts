<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Checkout\CheckoutStateStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
 * All checkout state lives in CheckoutStateStore (cache, keyed by checkout id)
 * — deliberately NOT in the session, whose whole-blob re-save on every request
 * let concurrent requests overwrite checkout progress, and which the stateless
 * mobile API never persists at all. The state is validated and transformed into
 * an Order at step 5.
 */
class CheckoutService
{
    public function __construct(
        private SequenceService $sequenceService,
        private SettingsService $settings,
        private CartService $cartService,
        private ShippingService $shippingService,
        private TaxRateService $taxRateService,
        private CouponService $couponService,
        private CheckoutStateStore $store,
    ) {}

    /**
     * Start a new checkout session for the given cart.
     * Returns the session key for the checkout.
     */
    public function start(Cart $cart): string
    {
        $checkoutId = Str::uuid()->toString();

        $this->store->put($checkoutId, [
            'cart_id' => $cart->id,
            'step' => 1,
            'data' => [
                'contact_email' => null,
                'contact_phone' => null,
                'guest_email' => null,
                'otp_verified' => false,
                'otp_pending_email' => null,
                'otp_pending_phone' => null,
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
        return $this->store->mutate($checkoutId, function (array &$checkout) use ($checkoutId): bool {
            $currentStep = (int) $checkout['step'];

            if (! $this->isStepComplete($checkoutId, $currentStep)) {
                return false;
            }

            $checkout['step'] = min((int) settings('checkout.max_steps', 5), $currentStep + 1);

            return true;
        });
    }

    /**
     * Move back one step, never below step 1.
     */
    public function goBack(string $checkoutId): bool
    {
        return $this->store->mutate($checkoutId, function (array &$checkout): bool {
            $checkout['step'] = max(1, ((int) $checkout['step']) - 1);

            return true;
        });
    }

    /**
     * Check whether a specific step is complete.
     */
    public function isStepComplete(string $checkoutId, int $step): bool
    {
        $checkout = $this->get($checkoutId);
        if (! $checkout) {
            return false;
        }

        $data = $checkout['data'];

        switch ($step) {
            case 1:
                return ! empty($data['contact_email']);
            case 2:
                return ! empty($data['shipping_address']);
            case 3:
                return ! empty($data['shipping_method_id']);
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
        return $this->store->get($checkoutId);
    }

    /**
     * Update data for the current step.
     */
    public function update(string $checkoutId, array $updates): bool
    {
        return $this->store->mutate($checkoutId, function (array &$checkout) use ($updates): bool {
            $allowed = [
                'step', 'shipping_address', 'billing_address', 'shipping_method_id', 'payment_method', 'notes',
                'coupon_code', 'coupon_id', 'discount_amount',
                'contact_email', 'contact_phone', 'guest_email', 'otp_verified', 'customer_note',
                'urgent_processing', 'otp_pending_email', 'otp_pending_phone', 'terms_accepted',
            ];

            foreach (['step', 'expires_at', 'created_at', 'cart_id'] as $topLevelKey) {
                if (array_key_exists($topLevelKey, $updates)) {
                    $checkout[$topLevelKey] = $updates[$topLevelKey];
                    unset($updates[$topLevelKey]);
                }
            }

            $checkout['data'] = array_merge($checkout['data'], array_intersect_key($updates, array_flip($allowed)));

            return true;
        });
    }

    /**
     * Clear the checkout state.
     */
    public function clear(string $checkoutId): void
    {
        $this->store->forget($checkoutId);
    }

    /**
     * Return a shipping method from the database by ID, or null.
     */
    private function getShippingMethod(?int $id): ?object
    {
        if (! $id) {
            return null;
        }

        $method = ShippingMethod::where('is_active', true)->find($id);

        if (! $method) {
            return null;
        }

        // trans_field() — the same multilingual-name resolver
        // CheckoutController's own copy of this lookup uses — not a hand
        // rolled 'en'-first fallback. The two independent name-resolution
        // implementations could silently diverge if trans_field()'s fallback
        // order ever changed, showing a different shipping method name on
        // the review page than what gets snapshotted onto the order.
        return (object) [
            'id' => $method->id,
            'name' => trans_field($method->name),
            'flat_rate' => $method->flat_rate,
            'estimated_days_min' => $method->estimated_days_min,
            'estimated_days_max' => $method->estimated_days_max,
        ];
    }

    /**
     * Create the final order from the checkout session.
     * Returns the Order model on success, throws on failure.
     *
     * @param  int|null  $userId  Explicit user ID — null for guest checkout
     * @param  string|null  $ipAddress  Client IP — null uses request()->ip()
     * @param  array  $utmParams  UTM tracking params — null reads from session
     */
    public function createOrder(
        string $checkoutId,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?array $utmParams = null,
    ): Order {
        return DB::transaction(function () use ($checkoutId, $userId, $ipAddress, $utmParams) {
            $checkout = $this->get($checkoutId);
            if (! $checkout) {
                throw new \RuntimeException('Checkout session expired or not found.');
            }

            // lockForUpdate(), not find(): a double-click on "Place order" (or a
            // client retry after a slow response) can otherwise fire two
            // concurrent createOrder() calls for the same cart — the database
            // session driver doesn't serialize concurrent requests for one
            // session the way file-session locking does. The second call now
            // blocks here until the first transaction finishes; if the first
            // committed, this cart row is already deleted (below) and the
            // second call cleanly fails with "Cart is empty or invalid"
            // instead of creating a second Order charged from the same cart.
            $cart = Cart::where('id', $checkout['cart_id'])->lockForUpdate()->first();
            if (! $cart || $cart->items->isEmpty()) {
                throw new \RuntimeException('Cart is empty or invalid.');
            }

            $data = $checkout['data'];

            // Enforced here, not just at the step4 endpoint that sets it:
            // the web flow can't reach this without having gone through
            // step4 first (store() always routes through the session's own
            // step counter — see CheckoutController@store — never a
            // client-claimed step), but the stateless mobile API exposes
            // every step as its own independently callable endpoint
            // (routes/api.php: POST .../step5 takes no dependency on
            // step4 ever having run), so nothing previously stopped a
            // client from calling step5 directly and completing a real,
            // chargeable order without ever agreeing to the terms.
            if (empty($data['terms_accepted'])) {
                throw new \RuntimeException('You must accept the terms and conditions before placing an order.');
            }

            $cart->loadMissing('items.product.manufacturer', 'items.product.condition');

            // Re-verify stock at the moment of charge, not just at add-to-cart
            // time: a product can go out of stock between add-to-cart and
            // checkout (admin deactivation, or a concurrent checkout for the
            // same item). lockForUpdate() here holds the row lock for the
            // rest of this transaction, so a second concurrent checkout for
            // the same product blocks until this one commits or rolls back
            // instead of both succeeding against the same unit of stock.
            foreach ($cart->items as $item) {
                $lockedProduct = Product::where('id', $item->product_id)->lockForUpdate()->first();
                if (! $lockedProduct || ! $lockedProduct->is_in_stock) {
                    $label = $item->product->oem_number ?? ('#'.$item->product_id);
                    throw new \RuntimeException("\"{$label}\" is no longer in stock and can't be ordered. Please remove it from your cart to continue.");
                }
            }

            $cartSummary = $this->cartService->getSummary($cart);
            $subtotal = bcadd((string) $cartSummary['subtotal'], '0', 2);
            $shippingCost = $this->calculateShippingCost(
                $cart,
                $data['shipping_method_id'],
                $data['shipping_address']['country_code'] ?? null
            );

            // Rush-processing upsell: re-check the merchant toggle at charge
            // time, not just the session flag — a customer's session could
            // predate an operator disabling the feature mid-checkout.
            $urgentProcessing = (bool) ($data['urgent_processing'] ?? false) && (bool) settings('rush_upsell.urgent_processing_enabled', false);
            $urgentProcessingFee = $urgentProcessing ? bcadd((string) settings('rush_upsell.urgent_processing_fee', '0.00'), '0', 2) : '0.00';
            $handlingFee = bcadd((string) settings('shipping.handling_fee', '0.00'), '0', 2);

            // Resolve context: explicit params or session/request helpers
            $resolvedUserId = $userId ?? auth()->id();
            $resolvedIp = $ipAddress ?? request()->ip();
            $utm = $utmParams ?? [
                'source' => session('utm_source'),
                'medium' => session('utm_medium'),
                'campaign' => session('utm_campaign'),
                'content' => session('utm_content'),
            ];

            // --- Coupon application ---
            // Re-validate against the cart's *current* subtotal and re-derive
            // the discount here, rather than trusting the discount_amount
            // CouponAjaxController cached in the checkout session whenever
            // the coupon was first applied — the customer can still change
            // cart contents afterwards (add/remove items, change quantity),
            // which would otherwise leave a stale discount amount (wrong
            // percentage-of, or one that no longer respects min_order_amount)
            // baked into the order. Resolved before the taxable base below —
            // VAT must be calculated on the post-discount amount, not the
            // full pre-discount subtotal (see calculateVat() call below).
            $couponId = $data['coupon_id'] ?? null;
            $discountAmount = '0.00';
            $coupon = null;

            if ($couponId) {
                $coupon = Coupon::find($couponId);
                if ($coupon) {
                    // Multi-account abuse check needs the REAL customer
                    // identity, not just $resolvedUserId (trivially reset
                    // by signing up again) — the authenticated user's own
                    // email if there is one, else whatever guest_email this
                    // checkout session collected in step1.
                    $resolvedEmail = $resolvedUserId
                        ? User::find($resolvedUserId)?->email
                        : ($data['guest_email'] ?? null);

                    $revalidated = $this->couponService->validateCoupon($coupon, $subtotal, $resolvedUserId, $resolvedEmail, $resolvedIp);
                    if ($revalidated['valid']) {
                        $discountAmount = $revalidated['discount'];
                    } else {
                        $couponId = null;
                        $coupon = null;
                    }
                } else {
                    $couponId = null;
                }
            }

            // EU VAT Directive Art. 79(b): a discount "allowed to the customer
            // and accounted for at the time of the supply" is excluded from
            // the VAT taxable amount — VAT is due on what's actually paid,
            // not the pre-discount list price. The discount only ever applies
            // against the product subtotal (CouponService::calculateDiscount()
            // caps it at $subtotal, never at shipping/fees), matching
            // CartService::getSummary()'s cart-page estimate, which already
            // computes VAT on subtotal-minus-discount — the order actually
            // charged here must not diverge from what the customer was shown.
            $discountedSubtotal = bcsub((string) $subtotal, $discountAmount, 2);
            $taxableBase = bcadd(bcadd(bcadd($discountedSubtotal, (string) $shippingCost, 2), $urgentProcessingFee, 2), $handlingFee, 2);
            $vatAmount = $this->calculateVat($taxableBase, $data['shipping_address']['country_code'] ?? null);

            $grandTotal = bcadd($taxableBase, $vatAmount, 2);
            // Floor at 0.00 (can't be negative)
            if (bccomp($grandTotal, '0.00', 2) === -1) {
                $grandTotal = '0.00';
            }

            $paymentMethod = PaymentMethod::BankTransfer; // default
            if (isset($data['payment_method'])) {
                $paymentMethod = match ($data['payment_method']) {
                    'card' => PaymentMethod::Card,
                    'paysera' => PaymentMethod::Paysera,
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
                'coupon_id' => $couponId,
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

            if ($coupon) {
                $this->couponService->apply($coupon, $order);
            }

            $cart->items()->delete();
            $cart->delete();

            $this->clear($checkoutId);

            if ($data['guest_email'] && ! $resolvedUserId) {
                $this->createGuestAccount($data['guest_email'], $order);
            }

            dispatch(new GenerateInvoicePdf($order));

            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    /**
     * Calculate shipping cost based on selected method from database.
     */
    public function calculateShippingCost(Cart $cart, ?int $shippingMethodId, ?string $destinationCountryCode = null): string
    {
        if (! $shippingMethodId) {
            return '0.00';
        }

        return $this->shippingService->calculateCost($cart, $shippingMethodId, $destinationCountryCode);
    }

    /**
     * Calculate VAT amount on a taxable base. Uses the destination country's
     * rate when Country-Based VAT is enabled (Settings → Tax Settings) and a
     * rate is configured for it; otherwise falls back to the flat store rate.
     */
    private function calculateVat(string $amount, ?string $countryCode = null): string
    {
        $vatRate = $this->taxRateService->resolve($countryCode);

        return bcmul($amount, bcdiv($vatRate, '100', 4), 2);
    }

    /**
     * Automatically create a user account for a guest after order placement.
     */
    private function createGuestAccount(string $email, Order $order): User
    {
        return DB::transaction(function () use ($email, $order) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $order->update(['user_id' => $user->id]);

                return $user;
            }

            $password = Str::random((int) settings('checkout.guest_password_length', 12));
            $user = User::create([
                'name' => 'Guest '.explode('@', $email)[0],
                'email' => $email,
                'password' => bcrypt($password),
            ]);
            // email_verified_at is intentionally not in User::$fillable, so
            // create() silently discarded it here — every auto-created guest
            // account was left permanently unverified despite the customer
            // having already proven ownership of the email (guest checkout
            // OTP, or a successful order). Set it via forceFill instead.
            $user->forceFill(['email_verified_at' => now()])->save();

            $order->update(['user_id' => $user->id]);

            return $user;
        });
    }
}
