<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService
    ) {}

    /**
     * Display the cart page.
     *
     * Route: /{lang}/cart
     */
    public function index(Request $request, string $lang)
    {
        $cartData = $this->getCurrentCart($request);
        $cart = $cartData['cart'];
        $summary = $this->cartService->getSummary($cart);

        return view('frontend.cart.index', [
            'cart' => $cart,
            'summary' => $summary,
            'priceChanges' => $summary['price_changes'],
        ]);
    }

    /**
     * API: Add item to cart.
     *
     * POST /{lang}/cart/add
     */
    public function add(Request $request, string $lang)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:999',
        ]);

        $cartData = $this->getCurrentCart($request);
        $cart = $cartData['cart'];
        $guestToken = $cartData['guest_token'];

        try {
            $item = $this->cartService->addItem(
                $cart,
                $request->product_id,
                $request->quantity
            );

            return $this->jsonResponse([
                'success' => true,
                'message' => __('cart.item_added'),
                'cart_summary' => $this->cartService->getSummary($cart),
                'item' => $item,
            ], 200, $guestToken);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422, $guestToken);
        }
    }

    /**
     * API: Remove item from cart.
     *
     * DELETE /{lang}/cart/remove/{item}
     */
    public function remove(Request $request, string $lang, int $itemId)
    {
        $cartData = $this->getCurrentCart($request);
        $cart = $cartData['cart'];

        $success = $this->cartService->removeItem($cart, $itemId);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => __('cart.item_not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('cart.item_removed'),
            'cart_summary' => $this->cartService->getSummary($cart),
        ]);
    }

    /**
     * API: Update item quantity.
     *
     * PUT /{lang}/cart/update/{item}
     */
    public function update(Request $request, string $lang, int $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0|max:999',
        ]);

        $cartData = $this->getCurrentCart($request);
        $cart = $cartData['cart'];

        try {
            $item = $this->cartService->updateQuantity($cart, $itemId, $request->quantity);

            if (!$item && $request->quantity == 0) {
                // Item was removed (quantity set to 0)
                return response()->json([
                    'success' => true,
                    'message' => __('cart.item_removed'),
                    'cart_summary' => $this->cartService->getSummary($cart),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => __('cart.quantity_updated'),
                'cart_summary' => $this->cartService->getSummary($cart),
                'item' => $item,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Get cart summary (for navbar badge, etc.)
     *
     * GET /{lang}/cart/summary
     */
    public function summary(Request $request, string $lang)
    {
        $cartData = $this->getCurrentCart($request);
        $cart = $cartData['cart'];
        $summary = $this->cartService->getSummary($cart);

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    /**
     * API: Mini-cart preview (items + summary for hover dropdown).
     *
     * GET /{lang}/cart/preview
     */
    public function preview(Request $request, string $lang)
    {
        $cartData = $this->getCurrentCart($request);
        $cart = $cartData['cart'];
        $cart->load('items.product');
        $summary = $this->cartService->getSummary($cart);

        $items = $cart->items->map(function ($item) {
            $product = $item->product;
            return [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'price' => (float) $product->price,
                'line_total' => (float) bcmul((string) $product->price, (string) $item->quantity, 2),
                'oem_number' => $product->oem_number,
                'name' => trans_field($product->name),
                'condition' => $product->condition,
            ];
        });

        return response()->json([
            'success' => true,
            'items' => $items,
            'summary' => $summary,
        ]);
    }

    /**
     * API: Merge guest cart with user cart (called after login).
     *
     * POST /{lang}/cart/merge
     */
    public function merge(Request $request, string $lang)
    {
        $request->validate([
            'guest_token' => 'required|string|size:32',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthenticated'),
            ], 401);
        }

        try {
            $cart = $this->cartService->mergeGuestCart($user, $request->guest_token);

            return response()->json([
                'success' => true,
                'message' => __('cart.merged'),
                'cart_summary' => $this->cartService->getSummary($cart),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * API: Apply coupon to cart.
     *
     * POST /{lang}/cart/coupon/apply
     */
    public function applyCoupon(Request $request, string $lang)
    {
        $request->validate([
            'coupon_code' => 'required|string|max:50',
        ]);

        $cartData = $this->getCurrentCart($request);
        $cart = $cartData['cart'];

        // Save code to cart to allow CartService to validate and build summary
        $cart->update(['coupon_code' => $request->coupon_code]);

        // Fetch summary (CartService will validate and nullify invalid coupons within getSummary)
        $summary = $this->cartService->getSummary($cart);

        if (!$cart->fresh()->coupon_code) {
            return response()->json([
                'success' => false,
                'message' => __('cart.invalid_coupon'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('cart.coupon_applied'),
            'cart_summary' => $summary,
        ]);
    }

    /**
     * API: Remove coupon from cart.
     *
     * DELETE /{lang}/cart/coupon/remove
     */
    public function removeCoupon(Request $request, string $lang)
    {
        $cartData = $this->getCurrentCart($request);
        $cart = $cartData['cart'];

        $cart->update(['coupon_code' => null]);

        return response()->json([
            'success' => true,
            'message' => __('cart.coupon_removed'),
            'cart_summary' => $this->cartService->getSummary($cart),
        ]);
    }

    /**
     * Get the current cart for the request (user or guest).
     * Returns the cart and optionally a new guest token if one was created.
     */
    private function getCurrentCart(Request $request): array
    {
        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');

        $cart = $this->cartService->getOrCreateCart($user, $guestToken);
        
        // If this is a guest cart and we didn't have a token before,
        // we need to return the new token to set as cookie
        $newGuestToken = null;
        if (!$user && !$guestToken && $cart->guest_token) {
            $newGuestToken = $cart->guest_token;
        }

        return ['cart' => $cart, 'guest_token' => $newGuestToken];
    }

    /**
     * Create a JSON response with optional guest token cookie.
     */
    private function jsonResponse(array $data, int $status = 200, ?string $guestToken = null)
    {
        $response = response()->json($data, $status);
        
        if ($guestToken) {
            $response->cookie('guest_token', $guestToken, 60 * 24 * 7); // 7 days
        }
        
        return $response;
    }
}