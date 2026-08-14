<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\SearchLog;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CheckoutService $checkoutService
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
        // Eager-load items for the view: the cart page serialises $cart via @js()
        // into the cartData() Alpine component, which reads $cart->items. Without
        // this the relation is absent from the JSON and the page renders as an
        // empty cart even when items exist (getSummary() reads items separately).
        $cart->load('items.product.condition');
        $summary = $this->cartService->getSummary($cart);

        $popularOems = Cache::remember('cart_popular_oems_' . $lang, 3600, function () {
            return SearchLog::selectRaw('normalized_query, COUNT(*) as hits')
                ->where('result_count', '>', 0)
                ->where('created_at', '>=', now()->subDays((int) settings('search.popular_days_window', 30)))
                ->groupBy('normalized_query')
                ->orderByDesc('hits')
                ->limit((int) settings('search.popular_limit', 8))
                ->pluck('normalized_query');
        });

        return view('frontend.cart.index', [
            'cart' => $cart,
            'summary' => $summary,
            'priceChanges' => $summary['price_changes'],
            'popularOems' => $popularOems,
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
            'quantity' => 'required|integer|min:1|max:' . settings('cart.max_quantity', 999),
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
     * "Buy Now" — skips the cart page entirely. Creates an ISOLATED,
     * throwaway Cart (own guest_token, never written to the guest_token
     * cookie, is_buy_now=true) containing just this one item, then feeds
     * it through the exact same CheckoutService::start()/CheckoutController
     * flow a normal cart would use — CheckoutService::createOrder() already
     * deletes whatever cart it completes, buy-now or not, so no special-
     * casing is needed there. The customer's real cart (their own
     * guest_token cookie or user-bound Cart row) is never read or written
     * here, so it stays completely untouched.
     *
     * POST /{lang}/cart/buy-now
     */
    public function buyNow(Request $request, string $lang)
    {
        if (! filter_var(settings('pdp.buy_now_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            abort(404);
        }

        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:' . settings('cart.max_quantity', 999),
        ]);

        $buyNowCart = Cart::create([
            'user_id' => null,
            'guest_token' => Str::random(32),
            'is_buy_now' => true,
            // checkout.timeout_minutes, NOT the longer cart.expiry_days — a
            // buy-now cart is short-lived by design, and this window means
            // the existing daily cart:clean sweep garbage-collects an
            // abandoned one with zero new cleanup code.
            'expires_at' => now()->addMinutes((int) settings('checkout.timeout_minutes', 30)),
        ]);

        try {
            $this->cartService->addItem($buyNowCart, (int) $request->product_id, (int) $request->quantity);
        } catch (\Exception $e) {
            $buyNowCart->delete();

            return back()->with('error', $e->getMessage());
        }

        $checkoutId = $this->checkoutService->start($buyNowCart);
        $request->session()->put('active_checkout_id', $checkoutId);

        return redirect()->route('frontend.checkout', ['lang' => $lang]);
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
    public function update(\App\Http\Requests\Frontend\CartUpdateRequest $request, string $lang, int $itemId)
    {
        $validated = $request->validated();
        $request->merge($validated);

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

        $items = $cart->items
            // A product already sitting in someone's cart can be
            // soft-deleted later (admin removes/discontinues it); CartItem::
            // product() has no withTrashed(), so it resolves to null and
            // this dropdown crashed for that user on every hover.
            ->filter(fn ($item) => $item->product !== null)
            ->values()
            ->map(function ($item) {
                $product = $item->product;
                return [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'price' => $product->price,
                    'line_total' => bcmul((string) $product->price, (string) $item->quantity, 2),
                    'oem_number' => $product->oem_number,
                    'name' => trans_field($product->name),
                    // The cart page's mapItem() reads stock to render the In/Out-of-stock
                    // flag; without this the row falls back to "Out of stock" after any
                    // update/remove (which re-hydrates items from this endpoint).
                    'is_in_stock' => (bool) $product->is_in_stock,
                    'condition_slug' => $product->condition?->slug ?? 'new',
                    'condition_name' => condition_label($product->condition),
                    'condition_bg' => $product->condition?->bg_color ?? '#DCFCE7',
                    'condition_text' => $product->condition?->text_color ?? '#16A34A',
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
        if (! filter_var(settings('cart.coupon_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'success' => false,
                'message' => __('cart.coupon_disabled'),
            ], 422);
        }

        $request->validate([
            'coupon_code' => 'required|string|max:50',
        ]);

        $cartData = $this->getCurrentCart($request);
        $cart = $cartData['cart'];

        // Save code to cart to allow CartService to validate and build summary
        $cart->update(['coupon_code' => $request->coupon_code]);
        \Cache::forget("cart_summary:{$cart->id}");

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
        \Cache::forget("cart_summary:{$cart->id}");

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
            $response->cookie('guest_token', $guestToken, (int) settings('cart.guest_cookie_days', 7) * 60 * 24);
        }
        
        return $response;
    }
}