<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cart Management Service
 *
 * Handles cart creation, item addition/removal, merging, and price change detection.
 * Supports both authenticated users and guest carts via guest_token.
 */
class CartService
{
    public function __construct(
        private SettingsService $settings,
        private TaxRateService $taxRateService
    ) {}

    /**
     * Get cart by checkout session ID.
     */
    public function getCartByCheckout(string $checkoutId): ?Cart
    {
        $checkout = session("checkout.{$checkoutId}");
        $cartId = $checkout['cart_id'] ?? null;
        if (! $cartId) {
            return null;
        }

        return Cart::with('items.product')->find($cartId);
    }

    /**
     * Get or create a cart for the current session/user.
     */
    public function getOrCreateCart(?User $user = null, ?string $guestToken = null): Cart
    {
        $expiryDays = (int) $this->settings->get('cart.expiry_days', 7);
        $expiresAt = Carbon::now()->addDays($expiryDays);

        if ($user) {
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['expires_at' => $expiresAt]
            );
        } else {
            if (! $guestToken) {
                $guestToken = Str::random(32);
            }

            $cart = Cart::firstOrCreate(
                ['guest_token' => $guestToken],
                ['expires_at' => $expiresAt]
            );
        }

        // Update expiry if cart is about to expire
        if ($cart->expires_at->lt(Carbon::now()->addDays(1))) {
            $cart->update(['expires_at' => $expiresAt]);
        }

        return $cart;
    }

    /**
     * Add a product to cart.
     *
     * @throws \RuntimeException if product not found or out of stock
     */
    public function addItem(Cart $cart, int $productId, int $quantity = 1): CartItem
    {
        $item = DB::transaction(function () use ($cart, $productId, $quantity) {
            $product = Product::lockForUpdate()->findOrFail($productId);

            if (! $product->is_in_stock) {
                throw new \RuntimeException('Product is out of stock.');
            }

            $existingItem = $cart->items()->where('product_id', $productId)->first();

            if ($existingItem) {
                // Bumping quantity on a product already in the cart never
                // grows the row count, so it must never be blocked by the
                // max-items cap below — that check only applies when this
                // add would create a genuinely new row. Also: price_at_add
                // is intentionally left untouched here — it's the price the
                // customer originally saw, and checkPriceChanges() relies on
                // it staying put to detect/warn about a price change since
                // then. Overwriting it on every quantity bump silently erased
                // that history.
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $quantity,
                ]);

                return $existingItem;
            }

            $maxItems = $this->settings->get('cart.max_items', 50);
            if ($cart->items()->count() >= $maxItems) {
                throw new \RuntimeException("Cart cannot have more than {$maxItems} items.");
            }

            return CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price_at_add' => $product->price,
            ]);
        });

        // Invalidate AFTER the transaction commits, not inside it. Forgetting
        // mid-transaction leaves a window, between the forget and the actual
        // commit, where a concurrent read (the navbar's own background
        // cart-count fetch on the very page the user is on is enough) still
        // sees the pre-write DB state, computes a stale/empty summary, and
        // re-populates the cache with it right after this forget already
        // ran — silently undoing the invalidation for the full 60s TTL. This
        // was the guest "add to cart then immediately view /cart shows
        // empty" race ([[project_bulletproof_testing_2026_09]]).
        Cache::forget("cart_summary:{$cart->id}");

        return $item;
    }

    /**
     * Remove an item from cart.
     */
    public function removeItem(Cart $cart, int $cartItemId): bool
    {
        $item = $cart->items()->where('id', $cartItemId)->first();
        if (! $item) {
            return false;
        }

        $deleted = $item->delete();
        if ($deleted) {
            Cache::forget("cart_summary:{$cart->id}");
        }

        return $deleted;
    }

    /**
     * Update item quantity.
     */
    public function updateQuantity(Cart $cart, int $cartItemId, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            $this->removeItem($cart, $cartItemId);

            return null;
        }

        $item = $cart->items()->where('id', $cartItemId)->first();
        if (! $item) {
            return null;
        }

        $product = $item->product;
        if (! $product->is_in_stock) {
            throw new \RuntimeException('Product is out of stock.');
        }

        $item->update(['quantity' => $quantity]);
        Cache::forget("cart_summary:{$cart->id}");

        return $item;
    }

    /**
     * Merge guest cart into user cart when user logs in.
     */
    public function mergeGuestCart(User $user, string $guestToken): Cart
    {
        $userCart = $this->getOrCreateCart($user);

        if (! filter_var(settings('cart.merge_on_login', true), FILTER_VALIDATE_BOOLEAN)) {
            return $userCart;
        }

        $guestCart = Cart::where('guest_token', $guestToken)->first();

        if (! $guestCart) {
            return $userCart;
        }

        DB::transaction(function () use ($userCart, $guestCart) {
            foreach ($guestCart->items as $guestItem) {
                try {
                    $this->addItem($userCart, $guestItem->product_id, $guestItem->quantity);
                } catch (\Exception $e) {
                    // Log error but continue merging other items
                    \Log::warning("Failed to merge cart item: {$e->getMessage()}");
                }
            }

            $guestCart->delete();
        });

        Cache::forget("cart_summary:{$userCart->id}");

        return $userCart;
    }

    /**
     * Check for price changes in cart items.
     *
     * @return array Array of items with significant price changes
     */
    public function checkPriceChanges(Cart $cart): array
    {
        $threshold = $this->settings->get('cart.price_change_threshold', 20);
        $changes = [];

        foreach ($cart->items as $item) {
            if (! $item->product) {
                continue;
            }
            $currentPrice = $item->product->price;
            $oldPrice = $item->price_at_add;

            if (bccomp((string) $oldPrice, '0') === 0) {
                continue;
            }

            $diff = bcsub((string) $currentPrice, (string) $oldPrice, 4);
            $absDiff = ltrim($diff, '-');
            $changePercent = bcmul(bcdiv($absDiff, (string) $oldPrice, 6), '100', 2);

            if ($changePercent >= $threshold) {
                $changes[] = [
                    'item' => $item,
                    'old_price' => bcadd((string) $oldPrice, '0', 2),
                    'current_price' => bcadd((string) $currentPrice, '0', 2),
                    'change_percent' => bcadd((string) $changePercent, '0', 2),
                    'block_checkout' => $changePercent >= $threshold,
                ];
            }
        }

        return $changes;
    }

    public function getSummary(Cart $cart): array
    {
        $cacheKey = "cart_summary:{$cart->id}";

        if (app()->environment('testing')) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 60, function () use ($cart) {
            // Always a FRESH load, never loadMissing(): most callers
            // (add/remove/update/merge/applyCoupon, Api\CartController,
            // CouponAjaxController) hand this method a $cart fetched before
            // whatever mutation just happened (e.g. update()/remove() load
            // the cart first, then change a CartItem row, then call this).
            // loadMissing() would see items.product already loaded from that
            // earlier, now-stale fetch and skip reloading — returning a
            // summary computed off pre-mutation data (confirmed by a real
            // test failure: item_count stayed at the old value after
            // update/remove once an eager-load was added upstream). A fresh
            // load() here is exactly 2 queries regardless of cart size (not
            // per-row N+1) and this whole block only runs on a cache miss
            // anyway, so the correctness this buys is effectively free.
            $cart->load('items.product');

            $subtotal = '0.00';
            $itemCount = 0;

            foreach ($cart->items as $item) {
                // A product already sitting in someone's cart can be
                // soft-deleted later (admin removes/discontinues it) — same
                // guard checkPriceChanges() below already applies, just
                // missing here, where it broke the cart entirely for anyone
                // holding a now-gone product.
                if (! $item->product) {
                    continue;
                }
                $lineTotal = bcmul((string) $item->product->price, (string) $item->quantity, 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $itemCount += $item->quantity;
            }

            // Apply coupon discount if any
            $couponCode = $cart->coupon_code;
            $couponDiscount = '0.00';
            $appliedCoupon = null;

            if ($couponCode) {
                // Same cache entry as CouponService::validate() (keyed on code
                // alone); is_active/expiry are checked here on the cached model
                // rather than folded into the cached query, so both callers
                // share one cache entry per code instead of two.
                $coupon = app(CacheService::class)->rememberCouponByCode(
                    $couponCode,
                    fn () => Coupon::where('code', $couponCode)->first(),
                );

                if ($coupon && (! $coupon->is_active || ($coupon->expires_at !== null && $coupon->expires_at->lt(Carbon::now())))) {
                    $coupon = null;
                }

                if ($coupon) {
                    // Check min order amount
                    $minAmount = $coupon->min_order_amount ?? 0;
                    if (bccomp($subtotal, (string) $minAmount) >= 0) {
                        // `discount_type` is cast to the DiscountType enum; normalise to its
                        // string value so this works whether an enum or a raw string is set.
                        $type = $coupon->discount_type instanceof DiscountType
                            ? $coupon->discount_type->value
                            : (string) $coupon->discount_type;

                        if ($type === DiscountType::Fixed->value) {
                            $couponDiscount = bccomp((string) $coupon->discount_value, $subtotal, 2) > 0 ? $subtotal : (string) $coupon->discount_value;
                        } elseif ($type === DiscountType::Percentage->value) {
                            $discountAmt = bcmul($subtotal, bcdiv((string) $coupon->discount_value, '100', 4), 2);
                            $couponDiscount = bccomp($discountAmt, $subtotal, 2) > 0 ? $subtotal : $discountAmt;
                        }
                        $couponDiscount = bcadd($couponDiscount, '0', 2);
                        $appliedCoupon = $couponCode;
                    } else {
                        $couponCode = null;
                    }
                } else {
                    $couponCode = null;
                }
            }

            // Subtotal after discount for VAT and Shipping calculation
            $discountedSubtotal = bcsub($subtotal, $couponDiscount, 2);

            // Pre-checkout the customer's shipping country/zone isn't known
            // yet, so this can't target one specific method's threshold —
            // uses the lowest active per-method free_shipping_threshold as a
            // "you might unlock free shipping" reference point. (Previously
            // read a settings key, `shipping.free_threshold`, that was never
            // seeded under that name — always fell back to 0, so this whole
            // progress block silently never rendered.)
            $freeShippingThreshold = (string) (ShippingMethod::where('is_active', true)
                ->whereNotNull('free_shipping_threshold')
                ->min('free_shipping_threshold') ?? 0);
            $shippingRemaining = bcsub($freeShippingThreshold, $discountedSubtotal, 2);
            $shippingNeeded = bccomp($shippingRemaining, '0', 2) > 0 ? $shippingRemaining : '0.00';

            // Pre-checkout the customer's shipping country isn't known yet
            // (same limitation as the free-shipping-threshold estimate above),
            // so this always resolves to the flat default rate — TaxRateService
            // still routes through here so there's one place VAT-rate logic
            // lives, not a second copy of the flat-rate fallback.
            $vatRate = $this->taxRateService->resolve(null);
            $vatAmount = bcmul($discountedSubtotal, bcdiv($vatRate, '100', 4), 2);
            $grandTotal = bcadd($discountedSubtotal, $vatAmount, 2);

            return [
                'item_count' => $itemCount,
                'subtotal' => $subtotal,
                'subtotal_excl_vat' => $subtotal,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'grand_total' => $grandTotal,
                'shipping_needed' => $shippingNeeded,
                'free_shipping_threshold' => $freeShippingThreshold,
                'coupon_code' => $appliedCoupon,
                'coupon_discount' => $couponDiscount,
                'price_changes' => $this->checkPriceChanges($cart),
            ];
        });
    }
}
