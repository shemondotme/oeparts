<?php

namespace App\Http\Controllers\Api;

use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * These endpoints previously declared a required `string $lang` second
 * parameter that no route in routes/api.php ever supplies (this API group
 * has no `{lang}` segment, unlike the Frontend/SSR routes these controllers
 * were likely adapted from) — every single call here threw an
 * ArgumentCountError before even reaching this class's own logic. Removed;
 * it was unused in every method body anyway.
 */
class CartController extends BaseApiController
{
    public function __construct(
        private CartService $cartService,
        private CouponService $couponService
    ) {}

    /**
     * Get cart summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');

        $cart = $this->cartService->getOrCreateCart($user, $guestToken);
        $summary = $this->cartService->getSummary($cart);

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    /**
     * Add item to cart.
     */
    public function add(Request $request): JsonResponse
    {
        // Rate limit: add requests per minute per IP
        $maxAdds = (int) settings('cart.rate_limit_per_minute', 60);
        if (!RateLimiter::attempt("cart:add:{$request->ip()}", $maxAdds, function () {
            return true;
        }, 60)) {
            throw new TooManyRequestsHttpException(60, 'Too many cart requests. Please slow down.');
        }

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:' . settings('cart.max_quantity', 99),
        ]);

        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');

        try {
            $cart = $this->cartService->getOrCreateCart($user, $guestToken);
            $this->cartService->addItem($cart, $validated['product_id'], $validated['quantity']);

            return response()->json([
                'success' => true,
                'message' => __('Item added to cart'),
                'itemCount' => $cart->items->sum('quantity'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update cart item quantity.
     */
    public function update(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:' . settings('cart.max_quantity', 99),
        ]);

        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);

        try {
            if ($validated['quantity'] <= 0) {
                $this->cartService->removeItem($cart, $itemId);
            } else {
                $this->cartService->updateQuantity($cart, $itemId, $validated['quantity']);
            }

            return response()->json([
                'success' => true,
                'message' => __('Cart updated'),
                'itemCount' => $cart->items->sum('quantity'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove item from cart.
     */
    public function remove(Request $request, int $itemId): JsonResponse
    {
        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);

        $this->cartService->removeItem($cart, $itemId);

        return response()->json([
            'success' => true,
            'message' => __('Item removed from cart'),
            'itemCount' => $cart->items->sum('quantity'),
        ]);
    }

    /**
     * Apply coupon to cart.
     *
     * CouponService::apply()/remove() operate on an Order at checkout time
     * (recording actual usage against a completed order) — a different
     * concern from staging a coupon on a still-open Cart, which is what this
     * endpoint needs. The previous code called apply($code, $cart) /
     * remove($cart) against those order-based signatures, throwing a
     * TypeError on every request. Mirrors the working pattern already used
     * by Frontend\CartController::applyCoupon()/removeCoupon(): validate,
     * then persist onto Cart::coupon_code (which CartService::getSummary()
     * already reads to compute the discount).
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);

        $summary = $this->cartService->getSummary($cart);
        $result = $this->couponService->validate($validated['code'], (string) $summary['subtotal'], $user?->id);

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        $cart->update(['coupon_code' => $validated['code']]);
        \Illuminate\Support\Facades\Cache::forget("cart_summary:{$cart->id}");

        return response()->json([
            'success' => true,
            'message' => __('Coupon applied successfully'),
            'summary' => $this->cartService->getSummary($cart),
        ]);
    }

    /**
     * Remove coupon from cart.
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);

        $cart->update(['coupon_code' => null]);
        \Illuminate\Support\Facades\Cache::forget("cart_summary:{$cart->id}");

        return response()->json([
            'success' => true,
            'message' => __('Coupon removed'),
            'summary' => $this->cartService->getSummary($cart),
        ]);
    }
}
