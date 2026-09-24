<?php

namespace App\Http\Controllers\Api;

use App\Enums\OtpPurpose;
use App\Models\Cart;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API Checkout Controller — stateless checkout flow for mobile apps.
 *
 * The mobile app stores the checkout_id locally and submits each step.
 * No server-side session is used for checkout state — it lives in
 * CheckoutStateStore, keyed by the checkout id, and each request must
 * prove it owns the underlying cart (see checkoutFor()).
 */
class CheckoutController extends BaseApiController
{
    public function __construct(
        private CheckoutService $checkoutService,
        private CartService $cartService,
        private OtpService $otpService,
    ) {}

    /**
     * The checkout for this id — but only if the caller owns its cart.
     *
     * Checkout state is keyed by an id the CLIENT holds ("the mobile app
     * stores the checkout_id locally"), so the id is a bearer credential:
     * without this, anyone who learned another shopper's id could read their
     * address/email or complete their order. It used to be implicitly private
     * only because the state sat in the owner's own session (which, for this
     * stateless API, meant it never persisted at all — see CheckoutStateStore).
     *
     * A checkout whose cart the caller doesn't own is reported exactly like a
     * missing one (404), so ids can't be probed for existence.
     */
    private function checkoutFor(Request $request, string $checkoutId): ?array
    {
        $checkout = $this->checkoutService->get($checkoutId);

        if (! $checkout) {
            return null;
        }

        $cart = Cart::find($checkout['cart_id']);

        if (! $cart) {
            return null;
        }

        $user = Auth::user();

        $owns = $cart->user_id !== null
            ? $user !== null && (int) $user->id === (int) $cart->user_id
            : $cart->guest_token !== null
                && hash_equals((string) $cart->guest_token, (string) $request->cookie('guest_token'));

        return $owns ? $checkout : null;
    }

    /**
     * Start a new checkout session.
     * POST /api/v1/checkout/start
     */
    public function start(Request $request): JsonResponse
    {
        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);

        if (! $cart || $cart->items->isEmpty()) {
            return $this->errorResponse('Cart is empty.', null, 422);
        }

        $checkoutId = $this->checkoutService->start($cart);
        $checkout = $this->checkoutService->get($checkoutId);

        return $this->createdResponse([
            'checkout_id' => $checkoutId,
            'step' => $checkout['step'],
            'expires_at' => $checkout['expires_at'],
        ], 'Checkout started');
    }

    /**
     * Get current checkout state.
     * GET /api/v1/checkout/{checkoutId}
     */
    public function show(Request $request, string $checkoutId): JsonResponse
    {
        $checkout = $this->checkoutFor($request, $checkoutId);

        if (! $checkout) {
            return $this->errorResponse('Checkout not found or expired.', null, 404);
        }

        return $this->successResponse([
            'checkout_id' => $checkoutId,
            'step' => $checkout['step'],
            'data' => $checkout['data'],
            'expires_at' => $checkout['expires_at'],
        ]);
    }

    /**
     * Step 1: Contact details + guest OTP.
     * POST /api/v1/checkout/{checkoutId}/step1
     */
    public function step1(Request $request, string $checkoutId): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'otp' => 'nullable|string',
        ]);

        $checkout = $this->checkoutFor($request, $checkoutId);
        if (! $checkout) {
            return $this->errorResponse('Checkout not found or expired.', null, 404);
        }

        $otpVerified = true;
        if (! Auth::user() && empty($validated['otp'])) {
            // Guest without OTP — auto-verify is handled by the frontend flow
            $otpVerified = false;
        }

        if (! empty($validated['otp'])) {
            // verify() returns a RESULT_* status string, not a bool — was being
            // assigned straight into otp_verified, so it was always truthy
            // (even 'invalid'/'expired') and 'guest_checkout' was passed as a
            // raw string where OtpPurpose (a backed enum) is required.
            $result = $this->otpService->verify(
                $validated['email'],
                $validated['otp'],
                OtpPurpose::GuestCheckout
            );
            $otpVerified = $result === OtpService::RESULT_OK;
        }

        $this->checkoutService->update($checkoutId, [
            'contact_email' => $validated['email'],
            'contact_phone' => $validated['phone'] ?? null,
            'guest_email' => ! Auth::user() ? $validated['email'] : null,
            'otp_verified' => $otpVerified,
        ]);

        // See the matching comment in Frontend\CheckoutController::
        // processStep1() — persisted onto the Cart itself (not just this
        // checkout session) so ProcessAbandonedCarts can reach a guest who
        // never completes checkout.
        if (! Auth::user()) {
            $cart = $this->cartService->getCartByCheckout($checkoutId);
            $cart?->update(['guest_email' => $validated['email']]);
        }

        $this->checkoutService->advance($checkoutId);

        return $this->successResponse([
            'checkout_id' => $checkoutId,
            'step' => $this->checkoutService->get($checkoutId)['step'],
        ], 'Step 1 complete');
    }

    /**
     * Step 2: Shipping address.
     * POST /api/v1/checkout/{checkoutId}/step2
     */
    public function step2(Request $request, string $checkoutId): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country_code' => 'required|string|size:2|in:AT,BE,BG,HR,CY,CZ,DK,EE,FI,FR,DE,GR,HU,IE,IT,LV,LT,LU,MT,NL,PL,PT,RO,SK,SI,ES,SE',
        ]);

        $checkout = $this->checkoutFor($request, $checkoutId);
        if (! $checkout) {
            return $this->errorResponse('Checkout not found or expired.', null, 404);
        }

        $shippingAddress = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'street' => $validated['street'],
            'city' => $validated['city'],
            'postal_code' => $validated['postal_code'],
            'country_code' => strtoupper($validated['country_code']),
        ];

        $this->checkoutService->update($checkoutId, [
            'shipping_address' => $shippingAddress,
        ]);
        $this->checkoutService->advance($checkoutId);

        return $this->successResponse([
            'checkout_id' => $checkoutId,
            'step' => $this->checkoutService->get($checkoutId)['step'],
        ], 'Step 2 complete');
    }

    /**
     * Step 3: Shipping method selection.
     * POST /api/v1/checkout/{checkoutId}/step3
     */
    public function step3(Request $request, string $checkoutId): JsonResponse
    {
        $validated = $request->validate([
            'shipping_method_id' => 'required|integer|exists:shipping_methods,id',
        ]);

        $checkout = $this->checkoutFor($request, $checkoutId);
        if (! $checkout) {
            return $this->errorResponse('Checkout not found or expired.', null, 404);
        }

        $this->checkoutService->update($checkoutId, [
            'shipping_method_id' => $validated['shipping_method_id'],
        ]);
        $this->checkoutService->advance($checkoutId);

        return $this->successResponse([
            'checkout_id' => $checkoutId,
            'step' => $this->checkoutService->get($checkoutId)['step'],
        ], 'Step 3 complete');
    }

    /**
     * Step 4: Review and accept terms.
     * POST /api/v1/checkout/{checkoutId}/step4
     */
    public function step4(Request $request, string $checkoutId): JsonResponse
    {
        $validated = $request->validate([
            'agree_terms' => 'required|accepted',
        ]);

        $checkout = $this->checkoutFor($request, $checkoutId);
        if (! $checkout) {
            return $this->errorResponse('Checkout not found or expired.', null, 404);
        }

        $this->checkoutService->update($checkoutId, ['terms_accepted' => true]);
        $this->checkoutService->advance($checkoutId);

        return $this->successResponse([
            'checkout_id' => $checkoutId,
            'step' => $this->checkoutService->get($checkoutId)['step'],
        ], 'Step 4 complete');
    }

    /**
     * Step 5: Place order.
     * POST /api/v1/checkout/{checkoutId}/step5
     */
    public function step5(Request $request, string $checkoutId): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:card,bank_transfer,paysera',
            'customer_note' => 'nullable|string|max:500',
        ]);

        $checkout = $this->checkoutFor($request, $checkoutId);
        if (! $checkout) {
            return $this->errorResponse('Checkout not found or expired.', null, 404);
        }

        $this->checkoutService->update($checkoutId, [
            'payment_method' => $validated['payment_method'],
            'customer_note' => $validated['customer_note'] ?? null,
        ]);

        // Create order with explicit params (API context)
        $user = Auth::user();
        try {
            $order = $this->checkoutService->createOrder(
                $checkoutId,
                $user?->id,
                $request->ip(),
            );
        } catch (\RuntimeException $e) {
            // Stock-availability / cart-state failures carry a safe,
            // customer-facing message (see CheckoutService::createOrder).
            return $this->errorResponse($e->getMessage(), null, 409);
        }

        return $this->createdResponse([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'grand_total' => $order->grand_total,
            'payment_method' => $order->payment_method->value,
        ], 'Order placed successfully');
    }
}
