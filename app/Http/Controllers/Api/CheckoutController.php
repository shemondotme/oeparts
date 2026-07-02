<?php

namespace App\Http\Controllers\Api;

use App\Services\CheckoutService;
use App\Services\CartService;
use App\Services\OtpService;
use App\Services\ViesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API Checkout Controller — stateless checkout flow for mobile apps.
 *
 * The mobile app stores the checkout_id locally and submits each step.
 * No server-side session is used for checkout state.
 */
class CheckoutController extends ApiController
{
    public function __construct(
        private CheckoutService $checkoutService,
        private CartService $cartService,
        private OtpService $otpService,
        private ViesService $viesService,
    ) {}

    /**
     * Start a new checkout session.
     * POST /api/v1/checkout/start
     */
    public function start(Request $request): JsonResponse
    {
        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);

        if (!$cart || $cart->items->isEmpty()) {
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
    public function show(string $checkoutId): JsonResponse
    {
        $checkout = $this->checkoutService->get($checkoutId);

        if (!$checkout) {
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

        $checkout = $this->checkoutService->get($checkoutId);
        if (!$checkout) {
            return $this->errorResponse('Checkout not found or expired.', null, 404);
        }

        $otpVerified = true;
        if (!Auth::user() && empty($validated['otp'])) {
            // Guest without OTP — auto-verify is handled by the frontend flow
            $otpVerified = false;
        }

        if (!empty($validated['otp'])) {
            $otpVerified = $this->otpService->verify(
                $validated['email'],
                $validated['otp'],
                'guest_checkout'
            );
        }

        $this->checkoutService->update($checkoutId, [
            'contact_email' => $validated['email'],
            'contact_phone' => $validated['phone'] ?? null,
            'guest_email' => !Auth::user() ? $validated['email'] : null,
            'otp_verified' => $otpVerified,
        ]);

        $this->checkoutService->advance($checkoutId);

        return $this->successResponse([
            'checkout_id' => $checkoutId,
            'step' => $this->checkoutService->get($checkoutId)['step'],
        ], 'Step 1 complete');
    }

    /**
     * Step 2: Shipping address + optional B2B info.
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
            'company_name' => 'nullable|string|max:255',
            'vat_number' => 'nullable|string|max:20',
        ]);

        $checkout = $this->checkoutService->get($checkoutId);
        if (!$checkout) {
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

        $updates = [
            'shipping_address' => $shippingAddress,
            'company_name' => $validated['company_name'] ?? null,
            'vat_number' => $validated['vat_number'] ?? null,
            'is_b2b' => !empty($validated['vat_number']),
        ];

        // Validate VAT via VIES if B2B
        if (!empty($validated['vat_number'])) {
            $countryCode = strtoupper(substr($validated['vat_number'], 0, 2));
            $vatNumber = substr($validated['vat_number'], 2);
            $result = $this->viesService->validate($countryCode, $vatNumber);
            $updates['vat_valid'] = $result->valid === true;
            $updates['vat_exempt'] = $result->valid === true;
        }

        $this->checkoutService->update($checkoutId, $updates);
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

        $checkout = $this->checkoutService->get($checkoutId);
        if (!$checkout) {
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

        $checkout = $this->checkoutService->get($checkoutId);
        if (!$checkout) {
            return $this->errorResponse('Checkout not found or expired.', null, 404);
        }

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
            'payment_method' => 'required|in:card,bank_transfer',
            'customer_note' => 'nullable|string|max:500',
        ]);

        $checkout = $this->checkoutService->get($checkoutId);
        if (!$checkout) {
            return $this->errorResponse('Checkout not found or expired.', null, 404);
        }

        $this->checkoutService->update($checkoutId, [
            'payment_method' => $validated['payment_method'],
            'customer_note' => $validated['customer_note'] ?? null,
        ]);

        // Create order with explicit params (API context)
        $user = Auth::user();
        $order = $this->checkoutService->createOrder(
            $checkoutId,
            $user?->id,
            $request->ip(),
        );

        return $this->createdResponse([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'grand_total' => $order->grand_total,
            'payment_method' => $order->payment_method->value,
        ], 'Order placed successfully');
    }
}
