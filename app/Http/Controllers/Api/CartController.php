<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CouponService $couponService
    ) {}

    /**
     * Get cart summary.
     */
    public function summary(Request $request, string $lang): JsonResponse
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
    public function add(Request $request, string $lang): JsonResponse
    {
        // Rate limit: 60 add requests per minute per IP
        if (!RateLimiter::attempt("cart:add:{$request->ip()}", 60, function () {
            return true;
        }, 60)) {
            throw new TooManyRequestsHttpException(60, 'Too many cart requests. Please slow down.');
        }

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:99',
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
    public function update(Request $request, string $lang, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:99',
        ]);

        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);

        try {
            if ($validated['quantity'] <= 0) {
                $this->cartService->removeItem($cart, $itemId);
            } else {
                $this->cartService->updateItemQuantity($cart, $itemId, $validated['quantity']);
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
    public function remove(Request $request, string $lang, int $itemId): JsonResponse
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
     */
    public function applyCoupon(Request $request, string $lang): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);

        try {
            $this->couponService->apply($validated['code'], $cart);

            return response()->json([
                'success' => true,
                'message' => __('Coupon applied successfully'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove coupon from cart.
     */
    public function removeCoupon(Request $request, string $lang): JsonResponse
    {
        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);

        $this->couponService->remove($cart);

        return response()->json([
            'success' => true,
            'message' => __('Coupon removed'),
        ]);
    }
}
