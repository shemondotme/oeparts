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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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
        // Get current user (if any) and guest token
        $user = Auth::user();
        $guestToken = $request->cookie('guest_token');
        
        // Ensure there is a cart with items
        $cart = $this->cartService->getOrCreateCart($user, $guestToken);
        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('frontend.cart.index', compact('lang'))
                ->with('error', __('Your cart is empty.'));
        }

        // Check if a checkout session already exists
        $checkoutId = Session::get('active_checkout_id');
        if (!$checkoutId) {
            // Start a new checkout session
            $checkoutId = $this->checkoutService->start($cart);
            Session::put('active_checkout_id', $checkoutId);
        }

        // Retrieve checkout state
        $checkout = $this->checkoutService->get($checkoutId);
        if (!$checkout) {
            // Session expired, restart
            $checkoutId = $this->checkoutService->start($cart);
            Session::put('active_checkout_id', $checkoutId);
            $checkout = $this->checkoutService->get($checkoutId);
        }

        $step = $checkout['step'];

        // Redirect to the appropriate step view
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
     * Step 1: Guest email + OTP.
     */
    private function showStep1(string $checkoutId, array $checkout, string $lang)
    {
        $data = $checkout['data'];
        $email = $data['guest_email'] ?? null;
        $otpSent = !empty($email) && $data['otp_verified'] === false;

        return view('frontend.checkout.step1', [
            'checkoutId' => $checkoutId,
            'email' => $email,
            'otpSent' => $otpSent,
            'lang' => $lang,
        ]);
    }

    private function processStep1(Request $request, string $checkoutId, string $lang)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'otp' => 'nullable|string|size:6',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $email = $request->input('email');
        $otp = $request->input('otp');

        // If OTP is provided, verify it
        if ($otp) {
            $result = $this->otpService->verify($email, $otp, OtpPurpose::GuestCheckout);
            if ($result !== OtpService::RESULT_OK) {
                return back()->with('error', $this->otpErrorMessage($result))
                    ->withInput();
            }

            // OTP verified
            $this->checkoutService->update($checkoutId, [
                'guest_email' => $email,
                'otp_verified' => true,
            ]);

            // Advance to step 2
            $this->checkoutService->advance($checkoutId);
            return redirect()->route('frontend.checkout', compact('lang'));
        }

        // No OTP yet – send OTP
        try {
            $this->otpService->generate($email, OtpPurpose::GuestCheckout, $request->ip());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $this->checkoutService->update($checkoutId, [
            'guest_email' => $email,
            'otp_verified' => false,
        ]);

        return back()->with('success', __('OTP sent to your email.'))->withInput();
    }

    /**
     * Step 2: B2B VAT validation (optional).
     */
    private function showStep2(string $checkoutId, array $checkout, string $lang)
    {
        $data = $checkout['data'];
        return view('frontend.checkout.step2', [
            'checkoutId' => $checkoutId,
            'is_b2b' => $data['is_b2b'] ?? false,
            'vat_number' => $data['vat_number'] ?? '',
            'company_name' => $data['company_name'] ?? '',
            'vat_exempt' => $data['vat_exempt'] ?? false,
            'lang' => $lang,
        ]);
    }

    private function processStep2(Request $request, string $checkoutId, string $lang)
    {
        $isB2b = $request->has('is_b2b') && $request->input('is_b2b') === '1';

        if (!$isB2b) {
            // Skip VAT validation
            $this->checkoutService->update($checkoutId, [
                'is_b2b' => false,
                'vat_number' => null,
                'vat_exempt' => false,
                'company_name' => null,
            ]);
            $this->checkoutService->advance($checkoutId);
            return redirect()->route('frontend.checkout', compact('lang'));
        }

        $validator = Validator::make($request->all(), [
            'vat_number' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $vatNumber = $request->input('vat_number');

        // Validate via VIES
        $result = $this->checkoutService->validateVat($checkoutId, $vatNumber);

        if ($result['valid'] ?? false) {
            $this->checkoutService->advance($checkoutId);
            return redirect()->route('frontend.checkout', compact('lang'))
                ->with('success', __('VAT number validated.'));
        }

        // VAT invalid or service unavailable – customer can proceed but will be charged VAT
        $this->checkoutService->update($checkoutId, [
            'is_b2b' => true,
            'vat_number' => $vatNumber,
            'vat_exempt' => false,
            'company_name' => $result['name'] ?? null,
        ]);

        $this->checkoutService->advance($checkoutId);
        return redirect()->route('frontend.checkout', compact('lang'))
            ->with('warning', __('VAT validation failed. VAT will be applied.'));
    }

    /**
     * Step 3: Shipping address.
     */
    private function showStep3(string $checkoutId, array $checkout, string $lang)
    {
        $data = $checkout['data'];
        $address = $data['shipping_address'] ?? [];

        return view('frontend.checkout.step3', [
            'checkoutId' => $checkoutId,
            'address' => $address,
            'lang' => $lang,
        ]);
    }

    private function processStep3(Request $request, string $checkoutId, string $lang)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'address_line1' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country_code' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $this->checkoutService->update($checkoutId, [
            'shipping_address' => $request->only([
                'name', 'address_line1', 'city', 'postal_code', 'country_code',
            ]),
        ]);

        $this->checkoutService->advance($checkoutId);
        return redirect()->route('frontend.checkout', compact('lang'));
    }

    /**
     * Step 4: Shipping method selection.
     */
    private function showStep4(string $checkoutId, array $checkout, string $lang)
    {
        $data = $checkout['data'];
        $countryCode = $data['shipping_address']['country_code'] ?? null;
        $selectedId = $data['shipping_method_id'] ?? null;

        // Fetch shipping methods for the selected country
        $methods = collect();
        if ($countryCode) {
            $methods = \App\Models\ShippingMethod::join('shipping_countries', 'shipping_methods.zone_id', '=', 'shipping_countries.zone_id')
                ->join('shipping_zones', 'shipping_methods.zone_id', '=', 'shipping_zones.id')
                ->where('shipping_countries.country_code', $countryCode)
                ->where('shipping_methods.is_active', true)
                ->where('shipping_zones.is_active', true)
                ->select('shipping_methods.*')
                ->orderBy('shipping_methods.sort_order')
                ->get();
        }

        // Check if free shipping threshold is met
        $cart = $this->cartService->getCartByCheckout($checkoutId);
        $cartTotal = $cart ? $cart->items->sum(fn($item) => $item->price_at_add * $item->quantity) : 0;
        $freeShippingThreshold = (float) settings('shipping.free_shipping_threshold', 150.00);
        $qualifiesForFreeShipping = $cartTotal >= $freeShippingThreshold;

        return view('frontend.checkout.step4', [
            'checkoutId' => $checkoutId,
            'methods' => $methods,
            'selectedId' => $selectedId,
            'lang' => $lang,
            'cartTotal' => $cartTotal,
            'freeShippingThreshold' => $freeShippingThreshold,
            'qualifiesForFreeShipping' => $qualifiesForFreeShipping,
        ]);
    }

    private function processStep4(Request $request, string $checkoutId, string $lang)
    {
        $validator = Validator::make($request->all(), [
            'shipping_method_id' => 'required|integer|exists:shipping_methods,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $this->checkoutService->update($checkoutId, [
            'shipping_method_id' => $request->input('shipping_method_id'),
        ]);

        $this->checkoutService->advance($checkoutId);
        return redirect()->route('frontend.checkout', compact('lang'));
    }

    /**
     * Step 5: Review & place order.
     */
    private function showStep5(string $checkoutId, array $checkout, string $lang)
    {
        $cart = $this->cartService->getOrCreateCart(null, request()->cookie('guest_token'));
        $summary = $this->cartService->getSummary($cart);
        $data = $checkout['data'];

        // Calculate totals (simplified)
        $subtotal = (string) ($summary['subtotal'] ?? '0.00');
        $shippingCost = '0.00'; // TODO: calculate based on selected method
        $vatRate = settings('tax.default_vat_rate', 21);
        $taxableBase = bcadd($subtotal, $shippingCost, 2);
        $vatAmount = $data['vat_exempt'] ? '0.00' : bcmul($taxableBase, bcdiv((string) $vatRate, '100', 4), 2);
        $grandTotal = bcadd($taxableBase, $vatAmount, 2);

        return view('frontend.checkout.step5', [
            'checkoutId' => $checkoutId,
            'cart' => $cart,
            'summary' => $summary,
            'data' => $data,
            'subtotal' => $subtotal,
            'shippingCost' => $shippingCost,
            'vatAmount' => $vatAmount,
            'grandTotal' => $grandTotal,
            'lang' => $lang,
        ]);
    }

    private function processStep5(Request $request, string $checkoutId, string $lang)
    {
        // Validate that all previous steps are complete
        if (!$this->checkoutService->isStepComplete($checkoutId, 5)) {
            return back()->with('error', __('Please complete all previous steps.'));
        }

        // Validate payment method selection
        $validated = $request->validate([
            'payment_method' => 'required|in:card,bank_transfer',
            'customer_note' => 'nullable|string|max:500',
            'urgent_processing' => 'boolean',
            'terms' => 'accepted',
        ]);

        // Store payment method and additional data
        $this->checkoutService->update($checkoutId, [
            'payment_method' => $validated['payment_method'],
            'customer_note' => $validated['customer_note'] ?? '',
            'urgent_processing' => $request->has('urgent_processing'),
        ]);

        try {
            $order = $this->checkoutService->createOrder($checkoutId);
        } catch (\Exception $e) {
            return back()->with('error', __('Order creation failed: ') . $e->getMessage());
        }

        // Clear active checkout ID
        Session::forget('active_checkout_id');

        // Redirect to payment page
        return redirect()->route('frontend.checkout.payment', [
            'lang' => $lang,
            'order' => $order->order_number,
        ]);
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
        
        // Get payment intent for Airwallex
        $airwallexIntent = null;
        if ($order->payment_method === PaymentMethod::Card) {
            $airwallexIntent = $this->paymentService->createAirwallexIntent($order);
        }
        
        // Get bank transfer details if applicable
        $bankTransferDetails = null;
        if ($order->payment_method === PaymentMethod::BankTransfer) {
            $bankTransferDetails = $this->paymentService->getBankTransferDetails($order);
        }
        
        return view('frontend.checkout.payment', [
            'order' => $order,
            'lang' => $lang,
            'airwallexIntent' => $airwallexIntent,
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
            'data' => $intent,
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
            
            return redirect()->route('frontend.checkout.payment.success', [
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
            return redirect()->route('frontend.checkout.payment.success', [
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
        
        return view('frontend.checkout.payment-success', [
            'order' => $order,
            'lang' => $lang,
            'payment' => $payment,
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
        $guestToken = request()->cookie('guest_token');
        
        // If user is logged in, check ownership
        if ($user) {
            if ($order->user_id !== $user->id) {
                abort(403, 'You do not have permission to access this payment.');
            }
            return;
        }
        
        // For guest, check guest token matches order's guest token
        if ($order->guest_token !== $guestToken) {
            abort(403, 'You do not have permission to access this payment.');
        }
    }

    /**
     * Thank you page.
     * GET /{lang}/checkout/thank-you/{order}
     */
    public function thankYou(string $lang, string $order)
    {
        $order = \App\Models\Order::where('order_number', $order)->firstOrFail();

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