<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\CouponService;
use App\Services\CartService;
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
        if (!$checkoutId) {
            return response()->json([
                'success' => false,
                'message' => 'No active checkout session.',
            ], 422);
        }

        $checkoutData = Session::get("checkout.{$checkoutId}.data", []);
        $cartId = Session::get("checkout.{$checkoutId}.cart_id");

        if (!$cartId) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found.',
            ], 422);
        }

        // Get cart and subtotal
        $cart = \App\Models\Cart::find($cartId);
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found.',
            ], 422);
        }

        $cartSummary = app(CartService::class)->getSummary($cart);
        $subtotal = $cartSummary['subtotal'];
        $userId = auth()->id();

        $result = app(CouponService::class)->validate($request->code, $subtotal, $userId);

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        // Store coupon in session
        Session::put("checkout.{$checkoutId}.data.coupon_id", $result['coupon']->id);
        Session::put("checkout.{$checkoutId}.data.discount_amount", $result['discount']);

        return response()->json([
            'success'   => true,
            'code'      => $request->code,
            'discount'  => $result['discount'],
            'message'   => null,
        ]);
    }

    /**
     * Remove applied coupon.
     */
    public function remove(Request $request, string $lang)
    {
        $checkoutId = Session::get('active_checkout_id');
        if (!$checkoutId) {
            return response()->json([
                'success' => false,
                'message' => 'No active checkout session.',
            ], 422);
        }

        app(CouponService::class)->remove($checkoutId);

        return response()->json(['success' => true]);
    }
}