<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use App\Services\CartService;
use App\Services\PaymentService;
use App\Services\ShippingService;
use App\Enums\PaymentMethod;
use App\Models\Order;
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
        private PaymentService $paymentService,
        private ShippingService $shippingService
    ) {}

    private function guestCheckoutAllowed(): bool
    {
        return filter_var(settings('auth.guest_checkout_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function guestOtpRequired(): bool
    {
        return app(\App\Services\OtpService::class)->enabled()
            && filter_var(settings('cart.otp_required_guest', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Entry point: start checkout or show current step.
     * GET /{lang}/checkout
     */
    public function index(Request $request, string $lang)
    {
        $user = Auth::user();

        if (!$user && !$this->guestCheckoutAllowed()) {
            return redirect()->route('frontend.cart.index', compact('lang'))
                ->with('error', __('checkout.guest_checkout_disabled'))
                ->with('show_auth_modal', true);
        }

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
        return $this->renderCheckoutStep('frontend.checkout.step1', $checkoutId, $checkout, $lang, [
            'otpPending' => !empty($checkout['data']['otp_pending_email']),
        ]);
    }

    private function processStep1(Request $request, string $checkoutId, string $lang)
    {
        $user = Auth::user();
        $isGuest = !$user;

        // Escape hatch from the OTP-pending sub-step: clear the pending
        // state and fall back to the plain email/phone form. No OTP is
        // generated/sent by this branch.
        if ($isGuest && $request->boolean('change_email')) {
            $this->checkoutService->update($checkoutId, [
                'otp_pending_email' => null,
                'otp_pending_phone' => null,
            ]);

            return redirect()->route('frontend.checkout', compact('lang'));
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'otp' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if ($isGuest && !$this->guestCheckoutAllowed()) {
            return redirect()->route('frontend.cart.index', compact('lang'))
                ->with('error', __('checkout.guest_checkout_disabled'))
                ->with('show_auth_modal', true);
        }

        $email = $request->input('email');
        $phone = $request->input('phone');

        if ($isGuest && $this->guestOtpRequired()) {
            $otpCode = $request->input('otp');
            $resend = $request->boolean('resend');
            $otpService = app(\App\Services\OtpService::class);

            if (empty($otpCode) || $resend) {
                // Generate the code — OtpService::generate() throws a
                // RuntimeException with a safe, pre-translated message for
                // the resend cooldown; that's fine to show verbatim.
                try {
                    $otp = $otpService->generate($email, \App\Enums\OtpPurpose::GuestCheckout, $request->ip());
                } catch (\RuntimeException $e) {
                    $this->checkoutService->update($checkoutId, [
                        'otp_pending_email' => $email,
                        'otp_pending_phone' => $phone,
                    ]);

                    return back()->with('error', $e->getMessage())->withInput();
                }

                // Sending the email is a separate failure mode — dispatch()
                // runs synchronously on the 'sync' queue connection (used in
                // local dev, and some shared-hosting installs per rule #41),
                // so a real SMTP failure throws right here. That used to be
                // caught by the RuntimeException block above (Symfony's
                // TransportException extends RuntimeException) and shown to
                // the customer as a raw, untranslated SMTP protocol error —
                // confirmed live via Playwright with a broken local SMTP
                // config. Report it and show a safe generic message instead,
                // matching the pattern already used for order-creation
                // failures below.
                try {
                    dispatch(new \App\Jobs\SendOtpEmail($email, $otp->otp_code, $lang));
                } catch (\Throwable $e) {
                    report($e);

                    $this->checkoutService->update($checkoutId, [
                        'otp_pending_email' => $email,
                        'otp_pending_phone' => $phone,
                    ]);

                    $message = __('checkout.otp_send_failed');
                    if (config('app.debug')) {
                        $message .= ' [debug: ' . $e->getMessage() . ']';
                    }

                    return back()->with('error', $message)->withInput();
                }

                $this->checkoutService->update($checkoutId, [
                    'otp_pending_email' => $email,
                    'otp_pending_phone' => $phone,
                ]);

                return back()->with('success', __('checkout.verification_code_sent'))->withInput();
            }

            // Verify OTP
            $result = $otpService->verify($email, $otpCode, \App\Enums\OtpPurpose::GuestCheckout);
            if ($result !== \App\Services\OtpService::RESULT_OK) {
                $this->checkoutService->update($checkoutId, [
                    'otp_pending_email' => $email,
                    'otp_pending_phone' => $phone,
                ]);

                return back()->withErrors(['otp' => $otpService->message($result)])->withInput();
            }
        }

        $this->checkoutService->update($checkoutId, [
            'contact_email' => $email,
            'contact_phone' => $phone,
            'guest_email' => $isGuest ? $email : null,
            'otp_verified' => true,
            'otp_pending_email' => null,
            'otp_pending_phone' => null,
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
        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country_code' => 'required|string|size:2',
            'is_b2b' => 'nullable|boolean',
            'company_name' => 'nullable|string|max:255',
            'vat_number' => 'nullable|string|max:50',
            'vat_valid' => 'nullable|boolean',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $vatValid = $request->boolean('vat_valid');
        $vatExempt = $vatValid && settings('tax.b2b_exempt_on_valid_vat', true);

        $this->checkoutService->update($checkoutId, [
            'shipping_address' => [
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'street' => $request->input('street'),
                'city' => $request->input('city'),
                'postal_code' => $request->input('postal_code'),
                'country_code' => $request->input('country_code'),
                'company_name' => $request->input('company_name'),
            ],
            'is_b2b' => $request->boolean('is_b2b'),
            'company_name' => $request->input('company_name'),
            'vat_number' => $request->input('vat_number'),
            'vat_valid' => $vatValid,
            'vat_exempt' => $vatExempt,
        ]);

        $this->checkoutService->advance($checkoutId);

        return redirect()->route('frontend.checkout', compact('lang'));
    }

    /**
     * Step 3: Shipping method selection.
     */
    private function showStep3(string $checkoutId, array $checkout, string $lang)
    {
        $data = $checkout['data'];

        return $this->renderCheckoutStep('frontend.checkout.step3', $checkoutId, $checkout, $lang, [
            'selectedId' => $data['shipping_method_id'] ?? null,
            'shippingOptions' => $this->buildShippingOptions($data['shipping_address']['country_code'] ?? null),
            'urgentProcessingEnabled' => (bool) settings('checkout.urgent_processing_enabled', false),
            'urgentProcessingFee' => (string) settings('checkout.urgent_processing_fee', '0.00'),
            'urgentProcessingSelected' => (bool) ($data['urgent_processing'] ?? false),
            'handlingFee' => (string) settings('shipping.handling_fee', '0.00'),
        ]);
    }

    /**
     * Active shipping methods for the step3 picker — was a raw query embedded
     * directly in the Blade view.
     *
     * Zone-restricted when the customer's shipping country resolves to a
     * configured zone (via ShippingCountry rows); falls back to every active
     * method when no zone is configured for the country (or none at all —
     * the common single-zone setup), so simple installs keep working exactly
     * as before.
     */
    private function buildShippingOptions(?string $countryCode): array
    {
        $methods = $countryCode
            ? $this->shippingService->getMethodsForCountry($countryCode)
            : collect();

        if ($methods->isEmpty()) {
            $methods = \App\Models\ShippingMethod::where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        return $methods
            ->map(function ($m) {
                // Display the localized name; keep icon-matching on the stable
                // English keyword so the heuristic works regardless of locale.
                $enName = strtolower(is_array($m->name) ? ($m->name['en'] ?? reset($m->name) ?? '') : (string) $m->name);
                $window = $this->shippingService->getEstimatedDeliveryWindow($m);

                return [
                    'id' => $m->id,
                    'name' => trans_field($m->name),
                    'days_min' => $m->estimated_days_min,
                    'days_max' => $m->estimated_days_max,
                    'price' => (float) $m->flat_rate,
                    'dispatch_date' => $window['dispatch_date'],
                    'delivery_earliest' => $window['earliest'],
                    'delivery_latest' => $window['latest'],
                    'dispatches_today' => $window['dispatches_today'],
                    'icon' => match(true) {
                        str_contains($enName, 'express') => 'rocket-launch',
                        str_contains($enName, 'economy') => 'globe-alt',
                        default => 'truck',
                    },
                ];
            })
            ->toArray();
    }

    private function processStep3(Request $request, string $checkoutId, string $lang)
    {
        $validIds = \App\Models\ShippingMethod::where('is_active', true)->pluck('id')->toArray();

        $validator = Validator::make($request->all(), [
            'shipping_method_id' => ['required', 'integer', Rule::in($validIds)],
        ], [
            'shipping_method_id.in' => __('Please select a valid shipping method.'),
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $this->checkoutService->update($checkoutId, [
            'shipping_method_id' => (int) $request->input('shipping_method_id'),
            // Re-checked against the merchant toggle again at order creation —
            // this is just what the customer opted into this session.
            'urgent_processing' => settings('checkout.urgent_processing_enabled', false) && $request->boolean('urgent_processing'),
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
            'customer_note' => 'nullable|string|max:' . settings('checkout.max_note_length', 500),
        ]);

        $this->checkoutService->update($checkoutId, [
            'payment_method' => $validated['payment_method'],
            'customer_note' => $validated['customer_note'] ?? null,
        ]);

        $checkout = $this->checkoutService->get($checkoutId);
        $cart = $this->cartService->getCartByCheckout($checkoutId);
        if ($cart) {
            $summary = $this->cartService->getSummary($cart);
            $minimumOrder = (float) settings('orders.minimum_order_amount', 0);
            if ($minimumOrder > 0 && $summary['subtotal'] < $minimumOrder) {
                return back()->with('error', __('Your order does not meet the minimum amount of :amount.', [
                    'amount' => format_price($minimumOrder),
                ]));
            }
        }

        try {
            $order = $this->checkoutService->createOrder($checkoutId);
        } catch (\Throwable $e) {
            report($e);

            $message = __('checkout.order_creation_failed');
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
        $selectedShippingMethod = null;
        $shippingCost = null;

        if (!empty($checkoutData['shipping_method_id'])) {
            $methodId = (int) $checkoutData['shipping_method_id'];
            $selectedShippingMethod = $this->getShippingMethod($methodId);
            $shippingCost = $selectedShippingMethod ? $this->checkoutService->calculateShippingCost($cart, $methodId) : null;
        }

        $sidebar = $this->buildSidebarSummary($checkoutData, $summary, $shippingCost);

        $expiresAt = \Carbon\Carbon::parse($checkout['expires_at']);
        $secondsRemaining = max(0, now()->diffInSeconds($expiresAt, false));

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
            'secondsRemaining' => $secondsRemaining,
        ]));
    }

    /**
     * Return a shipping method from the database by ID, or null.
     */
    private function getShippingMethod(int $id): ?object
    {
        $method = \App\Models\ShippingMethod::where('is_active', true)->find($id);

        if (! $method) {
            return null;
        }

        $name = trans_field($method->name);

        return (object) [
            'id' => $method->id,
            'name' => $name,
            'flat_rate' => $method->flat_rate,
            'estimated_days_min' => $method->estimated_days_min,
            'estimated_days_max' => $method->estimated_days_max,
        ];
    }

    private function buildSidebarSummary(array $checkoutData, ?array $summary, ?string $shippingCost): array
    {
        $subtotal = number_format((float) ($summary['subtotal'] ?? '0'), 2, '.', '');
        $couponDiscount = number_format((float) ($summary['coupon_discount'] ?? '0'), 2, '.', '');
        $discountedSubtotal = bcsub($subtotal, $couponDiscount, 2);
        $vatRate = (string) ($summary['vat_rate'] ?? settings('tax.default_vat_rate', 21));
        $shipping = $shippingCost ?? '0.00';

        $urgentProcessing = (bool) ($checkoutData['urgent_processing'] ?? false) && (bool) settings('checkout.urgent_processing_enabled', false);
        $urgentFee = $urgentProcessing ? bcadd((string) settings('checkout.urgent_processing_fee', '0.00'), '0', 2) : '0.00';
        $handlingFee = bcadd((string) settings('shipping.handling_fee', '0.00'), '0', 2);
        $fees = bcadd($urgentFee, $handlingFee, 2);

        $vatBase = $shippingCost !== null
            ? bcadd(bcadd($discountedSubtotal, $shipping, 2), $fees, 2)
            : bcadd($discountedSubtotal, $fees, 2);
        $vatAmount = ($checkoutData['vat_exempt'] ?? false)
            ? '0.00'
            : bcmul($vatBase, bcdiv($vatRate, '100', 4), 2);
        $grandTotal = $shippingCost !== null
            ? bcadd($vatBase, $vatAmount, 2)
            : bcadd(bcadd($discountedSubtotal, $fees, 2), $vatAmount, 2);

        return [
            'subtotal' => $subtotal,
            'coupon_discount' => $couponDiscount,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'shipping_cost' => $shippingCost,
            'urgent_processing' => $urgentProcessing,
            'urgent_processing_fee' => $urgentFee,
            'handling_fee' => $handlingFee,
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
                'message' => settings_trans('checkout.payment_error_message', 'Payment method is not card'),
            ], 400);
        }
        
        $intent = $this->paymentService->createAirwallexIntent($order);
        
        return response()->json([
            'success' => true,
            'payment_intent_id' => $intent['payment_intent_id'],
            'client_secret' => $intent['client_secret'],
            'payment_id' => $intent['payment_id'],
            'env' => settings('payment.airwallex_environment', 'sandbox') === 'live' ? 'prod' : 'demo',
            'currency' => settings('store.currency', 'EUR'),
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
        
        $rawAllowedPaymentMethods = settings('checkout.allowed_payment_methods', ['card', 'bank_transfer']);
        $allowedPaymentMethods = is_string($rawAllowedPaymentMethods)
            ? (json_decode($rawAllowedPaymentMethods, true) ?: ['card', 'bank_transfer'])
            : (array) $rawAllowedPaymentMethods;

        $validated = $request->validate([
            'payment_method' => 'required|in:' . implode(',', $allowedPaymentMethods),
            'payment_intent_id' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
            'payment_proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:' . (settings('checkout.proof_max_size_kb', 5120)),
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
            if (!$payment) {
                $payment = $order->payment()->create([
                    'order_id' => $order->id,
                    'gateway' => \App\Enums\PaymentGateway::BankTransfer,
                    'status' => \App\Enums\PaymentTransactionStatus::Pending,
                    'amount' => $order->grand_total,
                    'gateway_response' => [],
                ]);
            }
            if ($payment) {
                $proofPath = null;
                if ($request->hasFile('payment_proof') && $request->file('payment_proof')->isValid()) {
                    $proofPath = $request->file('payment_proof')->store('payment-proofs', 'public');
                }

                $payment->update([
                    'status' => 'pending',
                    'gateway_response' => [
                        'reference' => $validated['payment_reference'] ?? '',
                        'method' => 'bank_transfer',
                        'proof_path' => $proofPath,
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
            'message' => settings_trans('checkout.payment_success_message', 'Payment processing initiated'),
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

}