<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Coupon;
use App\Enums\DiscountType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Cart Management Service
 *
 * Handles cart creation, item addition/removal, merging, and price change detection.
 * Supports both authenticated users and guest carts via guest_token.
 */
class CartService
{
    public function __construct(
        private SettingsService $settings
    ) {}

    /**
     * Get cart by checkout session ID.
     *
     * @param string $checkoutId
     * @return \App\Models\Cart|null
     */
    public function getCartByCheckout(string $checkoutId): ?Cart
    {
        $checkout = session("checkout.{$checkoutId}");
        $cartId = $checkout['cart_id'] ?? null;
        if (!$cartId) {
            return null;
        }

        return Cart::with('items.product')->find($cartId);
    }

    /**
     * Get or create a cart for the current session/user.
     *
     * @param \App\Models\User|null $user
     * @param string|null $guestToken
     * @return \App\Models\Cart
     */
    public function getOrCreateCart(?User $user = null, ?string $guestToken = null): Cart
    {
        $expiryDays = (int) $this->settings->get('cart.expiry_days', 7);
        $expiresAt = Carbon::now()->addDays($expiryDays);

        if ($user) {
            // Authenticated user: find or create cart for user
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['expires_at' => $expiresAt]
            );
        } else {
            // Guest: require guest token
            if (!$guestToken) {
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
     * @param \App\Models\Cart $cart
     * @param int $productId
     * @param int $quantity
     * @return \App\Models\CartItem
     * @throws \Exception if product not found or out of stock
     */
    public function addItem(Cart $cart, int $productId, int $quantity = 1): CartItem
    {
        $maxItems = $this->settings->get('cart.max_items', 50);
        if ($cart->items()->count() >= $maxItems) {
            throw new \Exception("Cart cannot have more than {$maxItems} items.");
        }

        $product = Product::findOrFail($productId);

        // Check if product is in stock
        if (!$product->is_in_stock) {
            throw new \Exception("Product is out of stock.");
        }

        // Check if item already exists in cart
        $existingItem = $cart->items()->where('product_id', $productId)->first();

        if ($existingItem) {
            // Update quantity
            $newQuantity = $existingItem->quantity + $quantity;
            $existingItem->update([
                'quantity' => $newQuantity,
                'price_at_add' => $product->price, // Update price to current
            ]);
            return $existingItem;
        }

        // Create new cart item
        return CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $productId,
            'quantity' => $quantity,
            'price_at_add' => $product->price,
        ]);
    }

    /**
     * Remove an item from cart.
     *
     * @param \App\Models\Cart $cart
     * @param int $cartItemId
     * @return bool
     */
    public function removeItem(Cart $cart, int $cartItemId): bool
    {
        $item = $cart->items()->where('id', $cartItemId)->first();
        if (!$item) {
            return false;
        }

        return $item->delete();
    }

    /**
     * Update item quantity.
     *
     * @param \App\Models\Cart $cart
     * @param int $cartItemId
     * @param int $quantity
     * @return \App\Models\CartItem|null
     */
    public function updateQuantity(Cart $cart, int $cartItemId, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            $this->removeItem($cart, $cartItemId);
            return null;
        }

        $item = $cart->items()->where('id', $cartItemId)->first();
        if (!$item) {
            return null;
        }

        // Check if product is still in stock
        $product = $item->product;
        if (!$product->is_in_stock) {
            throw new \Exception("Product is out of stock.");
        }

        $item->update(['quantity' => $quantity]);
        return $item;
    }

    /**
     * Merge guest cart into user cart when user logs in.
     *
     * @param \App\Models\User $user
     * @param string $guestToken
     * @return \App\Models\Cart
     */
    public function mergeGuestCart(User $user, string $guestToken): Cart
    {
        $userCart = $this->getOrCreateCart($user);
        $guestCart = Cart::where('guest_token', $guestToken)->first();

        if (!$guestCart) {
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

            // Delete guest cart after merge
            $guestCart->delete();
        });

        return $userCart;
    }

    /**
     * Check for price changes in cart items.
     *
     * @param \App\Models\Cart $cart
     * @return array Array of items with significant price changes
     */
    public function checkPriceChanges(Cart $cart): array
    {
        $threshold = $this->settings->get('cart.price_change_threshold', 20);
        $changes = [];

        foreach ($cart->items as $item) {
            $currentPrice = $item->product->price;
            $oldPrice = $item->price_at_add;

            if ($oldPrice == 0) continue;

            $diff = bcsub((string) $currentPrice, (string) $oldPrice, 4);
            $absDiff = ltrim($diff, '-');
            $changePercent = (float) bcmul(bcdiv($absDiff, (string) $oldPrice, 6), '100', 2);

            if ($changePercent >= $threshold) {
                $changes[] = [
                    'item' => $item,
                    'old_price' => $oldPrice,
                    'current_price' => $currentPrice,
                    'change_percent' => $changePercent,
                    'block_checkout' => $changePercent >= $threshold,
                ];
            }
        }

        return $changes;
    }

    public function getSummary(Cart $cart): array
    {
        $subtotal = '0.00';
        $itemCount = 0;

        foreach ($cart->items as $item) {
            $lineTotal = bcmul((string) $item->product->price, (string) $item->quantity, 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);
            $itemCount += $item->quantity;
        }

        // Apply coupon discount if any
        $couponCode = $cart->coupon_code;
        $couponDiscount = '0.00';
        $appliedCoupon = null;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)
                ->where('is_active', true)
                ->where(function($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>=', Carbon::now());
                })
                ->first();

            if ($coupon) {
                // Check min order amount
                $minAmount = $coupon->min_order_amount ?? 0;
                if ($subtotal >= $minAmount) {
                    // `discount_type` is cast to the DiscountType enum; normalise to its
                    // string value so this works whether an enum or a raw string is set.
                    $type = $coupon->discount_type instanceof DiscountType
                        ? $coupon->discount_type->value
                        : (string) $coupon->discount_type;

                    if ($type === DiscountType::Fixed->value) {
                        $couponDiscount = min((string)$coupon->discount_value, $subtotal); // can't discount more than subtotal
                    } elseif ($type === DiscountType::Percentage->value) {
                        $discountAmt = bcmul($subtotal, bcdiv((string)$coupon->discount_value, '100', 4), 2);
                        $couponDiscount = min($discountAmt, $subtotal);
                    }
                    // Format to exactly 2 decimals
                    $couponDiscount = number_format((float)$couponDiscount, 2, '.', '');
                    $appliedCoupon = $couponCode;
                } else {
                    // Invalidated by min order amount, optionally remove from cart
                    $cart->update(['coupon_code' => null]);
                    $couponCode = null;
                }
            } else {
                $cart->update(['coupon_code' => null]);
                $couponCode = null;
            }
        }

        // Subtotal after discount for VAT and Shipping calculation
        $discountedSubtotal = bcsub($subtotal, $couponDiscount, 2);

        $freeShippingThreshold = (string) $this->settings->get('shipping.free_threshold', 0);
        $shippingRemaining = bcsub($freeShippingThreshold, $discountedSubtotal, 2);
        $shippingNeeded = bccomp($shippingRemaining, '0', 2) > 0 ? $shippingRemaining : '0.00';

        $vatRate = (string) $this->settings->get('tax.default_vat_rate', 21);
        $vatAmount = bcmul($discountedSubtotal, bcdiv($vatRate, '100', 4), 2);
        $grandTotal = bcadd($discountedSubtotal, $vatAmount, 2);

        return [
            'item_count' => $itemCount,
            'subtotal' => (float) $subtotal, // Subtotal BEFORE discount
            'subtotal_excl_vat' => (float) $subtotal,
            'vat_rate' => (float) $vatRate,
            'vat_amount' => (float) $vatAmount,
            'grand_total' => (float) $grandTotal,
            'shipping_needed' => (float) $shippingNeeded,
            'free_shipping_threshold' => (float) $freeShippingThreshold,
            'coupon_code' => $appliedCoupon,
            'coupon_discount' => (float) $couponDiscount,
            'price_changes' => $this->checkPriceChanges($cart),
        ];
    }

    /**
     * Clear expired carts (run via scheduled command).
     *
     * @return int Number of carts deleted
     */
    public function clearExpiredCarts(): int
    {
        return Cart::where('expires_at', '<', Carbon::now())->delete();
    }
}