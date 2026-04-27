<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use App\Services\CartService;
use App\Services\OtpService;
use App\Services\PaymentService;
use App\Enums\OtpPurpose;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService,
        private CartService $cartService,
        private OtpService $otpService,
        private PaymentService $paymentService
    ) {}

    /**
     * Entry point: start checkout or show current step.
     * GET /{lang}/checkout
     */
    public function index(Request $request, string $lang)
    {
        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('frontend.cart.index', compact('lang'))
                ->with('error', __('Your cart is empty.'));
        }

        $checkoutId = Session::get('active_checkout_id');
        if (!$checkoutId) {
            $checkoutId = $this->checkoutService->start($cart);
            Session::put('active_checkout_id', $checkoutId);
        }

        $checkout = $this->checkoutService->get($checkoutId);
        if (!$checkout) {
            $checkoutId = $this->checkoutService->start($cart);
            Session::put('active_checkout_id', $checkoutId);
            $checkout = $this->checkoutService->get($checkoutId);
        }

        if ($request->query('_back') === '1') {
            $this->checkoutService->goBack($checkoutId);
            $checkout = $this->checkoutService->get($checkoutId);
        }

        $step = $checkout['step'];

        return match ($step) {
            1 => $this->showStep1($checkoutId, $checkout, $lang),
            2 => $this->showStep2($checkoutId, $checkout, $lang),
            3 => $this->showStep3($checkoutId, $checkout, $lang),
            4 => $this->showStep4($checkoutId, $checkout, $lang),
            5 => $this->showStep5($checkoutId, $checkout, $lang),
            default => abort(500, 'Invalid checkout step'),
        };
    }

    /**
     * Process POST data for the current step and advance.
     * POST /{lang}/checkout
     */
    public function store(Request $request, string $lang)
    {
        $checkoutId = Session::get('active_checkout_id');
        if (!$checkoutId) {
            return redirect()->route('frontend.checkout', compact('lang'))
                ->with('error', __('Checkout session expired.'));
        }

        $checkout = $this->checkoutService->get($checkoutId);
        if (!$checkout) {
            return redirect()->route('frontend.checkout', compact('lang'))
                ->with('error', __('Checkout session expired.'));
        }

        $step = $checkout['step'];

        $method = 'processStep' . $step;
        if (method_exists($this, $method)) {
            return $this->$method($request, $checkoutId, $lang);
        }

        return back()->with('error', __('Invalid step.'));
    }

    /**
     * Step 1: Contact details and guest OTP verification.
     */
    private function showStep1(string $checkoutId, array $checkout, string $lang)
    {
        return $this->renderCheckoutStep('frontend.checkout.step1', $checkoutId, $checkout, $lang);
    }

    private function processStep1(Request $request, string $checkoutId, string $lang)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'is_b2b' => 'nullable|boolean',
            'otp_verified' => 'nullable|boolean',
            'otp' => 'nullable|string|size:6',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = Auth::user();
        $isGuest = !$user;
        $otpValue = $request->input('otp');
        $otpVerified = !$isGuest || $request->boolean('otp_verified');

        // Email OTP verification for guest checkout.
        // PRODUCTION: OTP is required and must be verified before proceeding.
        // Local dev can skip via CHECKOUT_SKIP_OTP=true in .env for faster testing.
        $skipOtp = (bool) config('app.checkout_skip_otp');

        if ($isGuest && $otpValue && !$skipOtp) {
            $result = $this->otpService->verify($request->input('email'), $otpValue, OtpPurpose::GuestCheckout);
            if ($result !== OtpService::RESULT_OK) {
                return back()->with('error', $this->otpErrorMessage($result))
                    ->withInput();
            }

            $otpVerified = true;
        }

        if ($skipOtp) {
            $otpVerified = true;
        }

        if ($isGuest && !$otpVerified) {
            try {
                $this->otpService->generate($request->input('email'), OtpPurpose::GuestCheckout, $request->ip());
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage())->withInput();
            }

            $this->checkoutService->update($checkoutId, [
                'contact_email' => $request->input('email'),
                'contact_phone' => $request->input('phone'),
                'guest_email' => $request->input('email'),
                'otp_verified' => false,
                'is_b2b' => $request->boolean('is_b2b'),
            ]);

            return back()->with('success', __('OTP sent to your email.'))->withInput();
        }

        $this->checkoutService->update($checkoutId, [
            'contact_email' => $request->input('email'),
            'contact_phone' => $request->input('phone'),
            'guest_email' => $isGuest ? $request->input('email') : null,
            'otp_verified' => $otpVerified,
            'is_b2b' => $request->boolean('is_b2b'),
            'vat_number' => $request->boolean('is_b2b') ? ($this->checkoutService->get($checkoutId)['data']['vat_number'] ?? null) : null,
            'vat_exempt' => $request->boolean('is_b2b') ? ($this->checkoutService->get($checkoutId)['data']['vat_exempt'] ?? false) : false,
            'company_name' => $request->boolean('is_b2b') ? ($this->checkoutService->get($checkoutId)['data']['company_name'] ?? null) : null,
        ]);

        $this->checkoutService->advance($checkoutId);

        return redirect()->route('frontend.checkout', compact('lang'));
    }

    /**
     * Step 2: Shipping address and optional B2B details.
     */
    private function showStep2(string $checkoutId, array $checkout, string $lang)
    {
        return $this->renderCheckoutStep('frontend.checkout.step2', $checkoutId, $checkout, $lang);
    }

    private function processStep2(Request $request, string $checkoutId, string $lang)
    {
        $checkout = $this->checkoutService->get($checkoutId);
        $isB2b = (bool) ($checkout['data']['is_b2b'] ?? false);

        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country_code' => 'required|string|size:2',
        ];

        if ($isB2b) {
            $rules['company'] = 'required|string|max:200';
            $rules['vat_number'] = 'required|string|max:20';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $updates = [
            'shipping_address' => [
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'street' => $request->input('street'),
                'city' => $request->input('city'),
                'postal_code' => $request->input('postal_code'),
                'country_code' => $request->input('country_code'),
            ],
            'is_b2b' => $isB2b,
            'company_name' => $isB2b ? $request->input('company') : null,
            'vat_number' => $isB2b ? $request->input('vat_number') : null,
            'vat_exempt' => false,
        ];

        $flashType = null;
        $flashMessage = null;

        if ($isB2b) {
            try {
                $result = $this->checkoutService->validateVat(
                    $checkoutId,
                    $request->input('vat_number'),
                    $request->input('country_code')
                );
                $updates['vat_exempt'] = (bool) ($result['valid'] ?? false);
                $updates['company_name'] = $result['name'] ?? $request->input('company');

                if ($updates['vat_exempt']) {
                    $flashType = 'success';
                    $flashMessage = __('VAT number validated.');
                } else {
                    $flashType = 'warning';
                    $flashMessage = __('VAT validation failed. VAT will be applied.');
                }
            } catch (\Throwable) {
                $flashType = 'warning';
                $flashMessage = __('VAT validation failed. VAT will be applied.');
            }
        }

        $this->checkoutService->update($checkoutId, $updates);
        $this->checkoutService->advance($checkoutId);

        $response = redirect()->route('frontend.checkout', compact('lang'));
        if ($flashType && $flashMessage) {
            $response->with($flashType, $flashMessage);
        }

        return $response;
    }

    /**
     * Step 3: Shipping method selection.
     */
    private function showStep3(string $checkoutId, array $checkout, string $lang)
    {
        $data = $checkout['data'];
        $countryCode = $data['shipping_address']['country_code'] ?? null;
        $methods = collect();

        if ($countryCode) {
            $methods = ShippingMethod::join('shipping_countries', 'shipping_methods.zone_id', '=', 'shipping_countries.zone_id')
                ->join('shipping_zones', 'shipping_methods.zone_id', '=', 'shipping_zones.id')
                ->where('shipping_countries.country_code', $countryCode)
                ->where('shipping_methods.is_active', true)
                ->where('shipping_zones.is_active', true)
                ->select('shipping_methods.*')
                ->orderBy('shipping_methods.sort_order')
                ->get();
        }

        return $this->renderCheckoutStep('frontend.checkout.step3', $checkoutId, $checkout, $lang, [
            'methods' => $methods,
            'selectedId' => $data['shipping_method_id'] ?? null,
        ]);
    }

    private function processStep3(Request $request, string $checkoutId, string $lang)
    {
        $checkout = $this->checkoutService->get($checkoutId);
        $countryCode = $checkout['data']['shipping_address']['country_code'] ?? null;
        $allowedMethodIds = $countryCode
            ? ShippingMethod::join('shipping_countries', 'shipping_methods.zone_id', '=', 'shipping_countries.zone_id')
                ->join('shipping_zones', 'shipping_methods.zone_id', '=', 'shipping_zones.id')
                ->where('shipping_countries.country_code', $countryCode)
                ->where('shipping_methods.is_active', true)
                ->where('shipping_zones.is_active', true)
                ->pluck('shipping_methods.id')
                ->all()
            : [];

        $validator = Validator::make($request->all(), [
            'shipping_method_id' => ['required', 'integer', Rule::in($allowedMethodIds)],
        ], [
            'shipping_method_id.in' => __('Please select a valid shipping method.'),
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $this->checkoutService->update($checkoutId, [
            'shipping_method_id' => (int) $request->input('shipping_method_id'),
        ]);

        $this->checkoutService->advance($checkoutId);
        return redirect()->route('frontend.checkout', compact('lang'));
    }

    /**
     * Step 4: Review order and accept terms.
     */
    private function showStep4(string $checkoutId, array $checkout, string $lang)
    {
        return $this->renderCheckoutStep('frontend.checkout.step4', $checkoutId, $checkout, $lang);
    }

    private function processStep4(Request $request, string $checkoutId, string $lang)
    {
        if (!$request->boolean('agree_terms')) {
            return back()->withErrors([
                'agree_terms' => __('Please accept the terms before continuing.'),
            ])->withInput();
        }

        $this->checkoutService->advance($checkoutId);
        return redirect()->route('frontend.checkout', compact('lang'));
    }

    /**
     * Step 5: Choose payment method and create order.
     */
    private function showStep5(string $checkoutId, array $checkout, string $lang)
    {
        return $this->renderCheckoutStep('frontend.checkout.step5', $checkoutId, $checkout, $lang);
    }

    private function processStep5(Request $request, string $checkoutId, string $lang)
    {
        if (!$this->checkoutService->isStepComplete($checkoutId, 4)) {
            return back()->with('error', __('Please complete all previous steps.'));
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:card,bank_transfer',
            'customer_note' => 'nullable|string|max:500',
        ]);

        $this->checkoutService->update($checkoutId, [
            'payment_method' => $validated['payment_method'],
            'customer_note' => $validated['customer_note'] ?? null,
        ]);

        try {
            $order = $this->checkoutService->createOrder($checkoutId);
        } catch (\Throwable $e) {
            report($e);

            $message = __('We could not create your order. Please try again.');
            if (config('app.debug')) {
                $message .= ' [debug: ' . $e->getMessage() . ']';
            }

            return back()->with('error', $message);
        }

        Session::forget('active_checkout_id');

        // Remember which orders this session owns so the guest can access the
        // payment / thank-you pages without an account (orders table has no
        // guest_token column).
        $ownedOrderIds = Session::get('owned_order_ids', []);
        $ownedOrderIds[] = $order->id;
        Session::put('owned_order_ids', array_values(array_unique($ownedOrderIds)));

        return redirect()->route('frontend.checkout.payment', [
            'lang' => $lang,
            'order' => $order->order_number,
        ]);
    }

    private function renderCheckoutStep(string $view, string $checkoutId, array $checkout, string $lang, array $data = [])
    {
        $checkoutData = $checkout['data'];
        $cart = $this->cartService->getCartByCheckout($checkoutId);
        $summary = $cart ? $this->cartService->getSummary($cart) : null;
        $selectedShippingMethod = !empty($checkoutData['shipping_method_id'])
            ? ShippingMethod::find($checkoutData['shipping_method_id'])
            : null;

        $shippingCost = null;
        if ($cart && $selectedShippingMethod) {
            $shippingCost = $this->checkoutService->calculateShippingCost($cart, $selectedShippingMethod->id);
        }

        $sidebar = $this->buildSidebarSummary($checkoutData, $summary, $shippingCost);

        return view($view, array_merge($data, [
            'checkoutId' => $checkoutId,
            'lang' => $lang,
            'step' => $checkout['step'],
            'checkoutData' => $checkoutData,
            'checkoutCart' => $cart,
            'checkoutSummary' => $summary,
            'selectedShippingMethod' => $selectedShippingMethod,
            'selectedShippingCost' => $shippingCost,
            'sidebarSummary' => $sidebar,
        ]));
    }

    private function buildSidebarSummary(array $checkoutData, ?array $summary, ?string $shippingCost): array
    {
        $subtotal = number_format((float) ($summary['subtotal'] ?? 0), 2, '.', '');
        $couponDiscount = number_format((float) ($summary['coupon_discount'] ?? 0), 2, '.', '');
        $discountedSubtotal = bcsub($subtotal, $couponDiscount, 2);
        $vatRate = (string) ($summary['vat_rate'] ?? settings('tax.default_vat_rate', 21));
        $shipping = $shippingCost ?? '0.00';
        $vatBase = $shippingCost !== null
            ? bcadd($discountedSubtotal, $shipping, 2)
            : $discountedSubtotal;
        $vatAmount = ($checkoutData['vat_exempt'] ?? false)
            ? '0.00'
            : bcmul($vatBase, bcdiv($vatRate, '100', 4), 2);
        $grandTotal = $shippingCost !== null
            ? bcadd($vatBase, $vatAmount, 2)
            : bcadd($discountedSubtotal, $vatAmount, 2);

        return [
            'subtotal' => $subtotal,
            'coupon_discount' => $couponDiscount,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'shipping_cost' => $shippingCost,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Payment page.
     * GET /{lang}/checkout/payment/{order}
     */
    public function payment(string $lang, string $order)
    {
        $order = \App\Models\Order::where('order_number', $order)->firstOrFail();
        
        // Ensure order belongs to current user or guest
        $this->authorizePaymentAccess($order);
        
        // Check if payment is already completed
        if ($order->payment_status === 'paid') {
            return redirect()->route('frontend.checkout.payment.success', [
                'lang' => $lang,
                'order' => $order->order_number,
            ]);
        }
        
        // Get bank transfer details if applicable
        $bankTransferDetails = null;
        if ($order->payment_method === PaymentMethod::BankTransfer) {
            $bankTransferDetails = $this->paymentService->getBankTransferDetails($order);
        }
        
        return view('frontend.checkout.payment', [
            'order' => $order,
            'lang' => $lang,
            'bankDetails' => $bankTransferDetails,
            'selectedMethod' => $order->payment_method?->value ?? 'card',
        ]);
    }
    
    /**
     * Get payment intent for Airwallex.
     * GET /{lang}/checkout/payment/{order}/intent
     */
    public function paymentIntent(string $lang, string $order)
    {
        $order = \App\Models\Order::where('order_number', $order)->firstOrFail();
        $this->authorizePaymentAccess($order);
        
        if ($order->payment_method !== PaymentMethod::Card) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method is not card',
            ], 400);
        }
        
        $intent = $this->paymentService->createAirwallexIntent($order);
        
        return response()->json([
            'success' => true,
            'payment_intent_id' => $intent['payment_intent_id'],
            'client_secret' => $intent['client_secret'],
            'payment_id' => $intent['payment_id'],
            'env' => settings('payment.airwallex_environment', 'sandbox') === 'live' ? 'prod' : 'demo',
            'currency' => 'EUR',
            'amount' => (int) bcmul((string) $order->grand_total, '100', 0),
        ]);
    }
    
    /**
     * Process payment submission.
     * POST /{lang}/checkout/payment/{order}/process
     */
    public function processPayment(Request $request, string $lang, string $order)
    {
        $order = \App\Models\Order::where('order_number', $order)->firstOrFail();
        $this->authorizePaymentAccess($order);
        
        $validated = $request->validate([
            'payment_method' => 'required|in:card,bank_transfer',
            'payment_intent_id' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
        ]);
        
        // Update order payment method if different
        if ($validated['payment_method'] !== $order->payment_method->value) {
            $order->update([
                'payment_method' => $validated['payment_method'],
            ]);
        }
        
        // For bank transfer, mark as pending with reference
        if ($validated['payment_method'] === 'bank_transfer') {
            $payment = $order->payment;
            if ($payment) {
                $payment->update([
                    'status' => 'pending',
                    'gateway_response' => [
                        'reference' => $validated['payment_reference'] ?? '',
                        'method' => 'bank_transfer',
                        'submitted_at' => now()->toISOString(),
                    ],
                ]);
            }

            // Land the customer on the thank-you page, which already shows
            // order details + bank-transfer next-step instructions.
            return redirect()->route('frontend.checkout.thank-you', [
                'lang' => $lang,
                'order' => $order->order_number,
            ]);
        }
        
        // For card payments, we rely on webhook for status updates
        return response()->json([
            'success' => true,
            'message' => 'Payment processing initiated',
            'redirect_url' => route('frontend.checkout.payment.return', [
                'lang' => $lang,
                'order' => $order->order_number,
            ]),
        ]);
    }
    
    /**
     * Handle return from Airwallex.
     * GET /{lang}/checkout/payment/{order}/return
     */
    public function paymentReturn(string $lang, string $order)
    {
        $order = \App\Models\Order::where('order_number', $order)->firstOrFail();
        $this->authorizePaymentAccess($order);
        
        // Check payment status via webhook
        $payment = $order->payment;
        if ($payment && $payment->status === 'paid') {
            return redirect()->route('frontend.checkout.thank-you', [
                'lang' => $lang,
                'order' => $order->order_number,
            ]);
        }

        // If still pending, show waiting page
        return view('frontend.checkout.payment-return', [
            'order' => $order,
            'lang' => $lang,
        ]);
    }
    
    /**
     * Payment success page.
     * GET /{lang}/checkout/payment/{order}/success
     */
    public function paymentSuccess(string $lang, string $order)
    {
        $order = \App\Models\Order::where('order_number', $order)->firstOrFail();
        $this->authorizePaymentAccess($order);

        // Ensure payment is at least pending or paid
        $payment = $order->payment;
        if (!$payment || ($payment->status !== 'paid' && $payment->status !== 'pending')) {
            return redirect()->route('frontend.checkout.payment', [
                'lang' => $lang,
                'order' => $order->order_number,
            ]);
        }

        // Redirect to the rich thank-you page (order + instructions already there).
        return redirect()->route('frontend.checkout.thank-you', [
            'lang' => $lang,
            'order' => $order->order_number,
        ]);
    }
    
    /**
     * Payment failed page.
     * GET /{lang}/checkout/payment/{order}/failed
     */
    public function paymentFailed(string $lang, string $order)
    {
        $order = \App\Models\Order::where('order_number', $order)->firstOrFail();
        $this->authorizePaymentAccess($order);
        
        return view('frontend.checkout.payment-failed', [
            'order' => $order,
            'lang' => $lang,
        ]);
    }
    
    /**
     * Helper: authorize payment access.
     */
    private function authorizePaymentAccess(Order $order): void
    {
        $user = Auth::user();

        // Session-owned orders (placed by this session, guest or user).
        $ownedOrderIds = (array) Session::get('owned_order_ids', []);
        if (in_array($order->id, $ownedOrderIds, true)) {
            return;
        }

        // Logged-in user must own the order.
        if ($user && $order->user_id === $user->id) {
            return;
        }

        abort(403, 'You do not have permission to access this payment.');
    }

    /**
     * Thank you page.
     * GET /{lang}/checkout/thank-you/{order}
     */
    public function thankYou(string $lang, string $order)
    {
        $order = \App\Models\Order::where('order_number', $order)->firstOrFail();

        $this->authorizePaymentAccess($order);

        return view('frontend.checkout.thank-you', [
            'order' => $order,
            'lang' => $lang,
        ]);
    }

    /**
     * Helper: convert OTP result code to user‑friendly message.
     */
    private function otpErrorMessage(string $result): string
    {
        return match ($result) {
            OtpService::RESULT_INVALID => __('Invalid OTP code.'),
            OtpService::RESULT_EXPIRED => __('OTP has expired. Request a new one.'),
            OtpService::RESULT_MAX_ATTEMPTS => __('Too many attempts. Request a new OTP.'),
            OtpService::RESULT_ALREADY_USED => __('This OTP has already been used.'),
            default => __('OTP verification failed.'),
        };
    }
}