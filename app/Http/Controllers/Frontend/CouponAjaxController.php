<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CouponAjaxController extends Controller
{
    /**
     * Apply a coupon code.
     */
    public function apply(Request $request, string $lang)
    {
        if (! filter_var(settings('cart.coupon_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'success' => false,
                'message' => __('cart.coupon_disabled'),
            ], 422);
        }

        $request->validate(['code' => 'required|string|max:50']);

        $checkoutId = Session::get('active_checkout_id');
        if (! $checkoutId) {
            return response()->json([
                'success' => false,
                'message' => 'No active checkout session.',
            ], 422);
        }

        $cartId = app(CheckoutService::class)->get($checkoutId)['cart_id'] ?? null;

        if (! $cartId) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found.',
            ], 422);
        }

        // Get cart and subtotal
        $cart = Cart::find($cartId);
        if (! $cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found.',
            ], 422);
        }

        $cartSummary = app(CartService::class)->getSummary($cart);
        $subtotal = $cartSummary['subtotal'];
        $userId = auth()->id();

        // A guest hasn't entered their email yet at this stage (that's
        // step1 of checkout) — email is null here for a guest, so this
        // preview only catches multi-account abuse by IP; the authoritative
        // check (all 3 signals) is CheckoutService::createOrder()'s own
        // re-validation at the moment the coupon is actually consumed.
        $result = app(CouponService::class)->validate($request->code, $subtotal, $userId, auth()->user()?->email, $request->ip());

        if (! $result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        // Store the coupon on the checkout state (not the session — see CheckoutStateStore).
        app(CheckoutService::class)->update($checkoutId, [
            'coupon_id' => $result['coupon']->id,
            'discount_amount' => $result['discount'],
        ]);

        return response()->json([
            'success' => true,
            'code' => $request->code,
            'discount' => $result['discount'],
            'message' => null,
        ]);
    }

    /**
     * Remove applied coupon.
     */
    public function remove(Request $request, string $lang)
    {
        $checkoutId = Session::get('active_checkout_id');
        if (! $checkoutId) {
            return response()->json([
                'success' => false,
                'message' => 'No active checkout session.',
            ], 422);
        }

        app(CouponService::class)->remove($checkoutId);

        return response()->json(['success' => true]);
    }
}
