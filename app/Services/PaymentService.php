<?php

namespace App\Services;

use App\Jobs\SendOrderConfirmationEmail;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PaymentService — handles payment processing for Airwallex and bank transfer.
 *
 * Responsibilities:
 *  - Create Airwallex payment intents
 *  - Generate bank transfer details (IBAN/BIC/Reference)
 *  - Verify webhook signatures (HMAC)
 *  - Process webhook events idempotently
 *  - Update order and payment statuses
 *
 * All financial calculations use bcmath (bcscale(2) set globally in AppServiceProvider).
 */
class PaymentService
{
    private const AIRWALLEX_API_BASE_SANDBOX = 'https://api-demo.airwallex.com/api/v1';
    private const AIRWALLEX_API_BASE_LIVE = 'https://api.airwallex.com/api/v1';

    // Paysera's docs list a single base URL for both sandbox and live —
    // environment is distinguished only by which client_id/client_secret
    // pair is configured, not by a different host.
    private const PAYSERA_API_BASE = 'https://api.paysera.com';
    private const PAYSERA_TOKEN_URL = 'https://api.paysera.com/auth/realms/Paysera/protocol/openid-connect/token';

    public function __construct(
        private SettingsService $settings,
        private OrderService $orderService,
    ) {}

    /**
     * Create an Airwallex payment intent for an order.
     *
     * Returns the client_secret and payment_intent_id for frontend iframe.
     */
    public function createAirwallexIntent(Order $order): array
    {
        $apiKey = $this->settings->get('payment.airwallex_api_key', '');
        $clientId = $this->settings->get('payment.airwallex_client_id', '');
        $environment = $this->settings->get('payment.airwallex_environment', 'sandbox');

        if (empty($apiKey) || empty($clientId)) {
            throw new \RuntimeException('Airwallex credentials not configured.');
        }

        $baseUrl = $environment === 'live' ? self::AIRWALLEX_API_BASE_LIVE : self::AIRWALLEX_API_BASE_SANDBOX;

        // Format amount: Airwallex expects smallest currency unit (cents for EUR)
        $amountCents = bcmul($order->grand_total, '100', 0);

        $manualCaptureEnabled = filter_var(
            $this->settings->get('payment.airwallex_manual_capture_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $payload = [
            'request_id' => Str::uuid()->toString(),
            'amount' => (string) $amountCents,
            'currency' => settings('general.currency', 'EUR'),
            'merchant_order_id' => $order->order_number,
            'customer' => [
                'email' => $order->guest_email ?? $order->user->email,
            ],
            'return_url' => route('frontend.checkout.thank-you', [
                'lang' => app()->getLocale(),
                'order' => $order->order_number,
            ]),
            // auto_capture defaults to true on Airwallex's side when omitted —
            // only send this when manual capture is actually enabled, so the
            // request shape for the (default) auto-capture path is unchanged.
            ...($manualCaptureEnabled ? [
                'payment_method_options' => [
                    'card' => ['auto_capture' => false],
                ],
            ] : []),
        ];

        try {
            $token = $this->airwallexAuthToken($baseUrl, $clientId, $apiKey);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->timeout(15)->retry(3, 1000)->post("{$baseUrl}/pa/payment_intents/create", $payload);

            $data = $response->json();

            if (!$response->successful() || !isset($data['client_secret'])) {
                Log::error('Airwallex payment intent creation failed', [
                    'order_id' => $order->id,
                    'response' => $data,
                ]);
                throw new \RuntimeException('Failed to create payment intent.');
            }

            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'gateway' => PaymentGateway::Airwallex,
                'transaction_id' => $data['id'] ?? null,
                'status' => PaymentTransactionStatus::Pending,
                'amount' => $order->grand_total,
                'gateway_response' => $data,
            ]);

            return [
                'client_secret' => $data['client_secret'],
                'payment_intent_id' => $data['id'],
                'payment_id' => $payment->id,
            ];
        } catch (\Exception $e) {
            Log::error('Airwallex API error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Payment gateway error: ' . $e->getMessage());
        }
    }

    /**
     * Exchange the configured Client ID / API Key for a short-lived bearer
     * token via Airwallex's own login endpoint. The raw API key is NOT a
     * bearer token — every other Airwallex REST call requires this exchange
     * first. Confirmed against the real sandbox API (POST
     * /api/v1/authentication/login with x-client-id/x-api-key headers
     * returns {token, expires_at}); the previous code skipped this and sent
     * the API key itself as "Authorization: Bearer {$apiKey}", which
     * Airwallex always rejects — every payment intent creation would have
     * failed. Tokens last ~30 minutes; cached for 25 to avoid a login round
     * trip on every checkout while staying safely inside the real expiry.
     */
    private function airwallexAuthToken(string $baseUrl, string $clientId, string $apiKey): string
    {
        $cacheKey = 'airwallex_auth_token:' . md5($baseUrl . $clientId);

        return Cache::remember($cacheKey, now()->addMinutes(25), function () use ($baseUrl, $clientId, $apiKey) {
            $response = Http::withHeaders([
                'x-client-id' => $clientId,
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post("{$baseUrl}/authentication/login", (object) []);

            if (! $response->successful() || ! $response->json('token')) {
                throw new \RuntimeException('Airwallex authentication failed: HTTP ' . $response->status());
            }

            return $response->json('token');
        });
    }

    /**
     * Capture a previously-authorized (held, not yet charged) Airwallex
     * payment intent. Only issues the capture call — the resulting Payment/
     * Order status update happens via the payment_intent.succeeded webhook
     * (processSuccessfulPayment()), same "webhook is the source of truth"
     * pattern the rest of this class already follows, and the same event
     * Airwallex fires for both an auto-captured AND a manually-captured
     * intent, so no separate handler is needed for that side of it.
     */
    public function captureAirwallexPayment(Payment $payment): void
    {
        if ($payment->gateway !== PaymentGateway::Airwallex) {
            throw new \RuntimeException('Payment is not an Airwallex payment.');
        }

        if ($payment->status !== PaymentTransactionStatus::Authorized) {
            throw new \RuntimeException('Payment is not in an authorized (held) state — nothing to capture.');
        }

        $apiKey = $this->settings->get('payment.airwallex_api_key', '');
        $clientId = $this->settings->get('payment.airwallex_client_id', '');
        $environment = $this->settings->get('payment.airwallex_environment', 'sandbox');
        $baseUrl = $environment === 'live' ? self::AIRWALLEX_API_BASE_LIVE : self::AIRWALLEX_API_BASE_SANDBOX;

        try {
            $token = $this->airwallexAuthToken($baseUrl, $clientId, $apiKey);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->timeout(15)->retry(3, 1000)->post(
                "{$baseUrl}/pa/payment_intents/{$payment->transaction_id}/capture",
                (object) []
            );

            if (!$response->successful()) {
                Log::error('Airwallex capture failed', [
                    'payment_id' => $payment->id,
                    'payment_intent_id' => $payment->transaction_id,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                throw new \RuntimeException('Failed to capture payment: HTTP '.$response->status());
            }

            Log::info('Airwallex capture requested successfully', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $payment->transaction_id,
            ]);
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Airwallex capture API error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Payment gateway error: '.$e->getMessage());
        }
    }

    /**
     * Process an Airwallex payment_intent.requires_capture webhook — the
     * customer has paid and funds are authorized/held, but not yet charged
     * (auto_capture was false). Does NOT mark the order paid or dispatch
     * PaymentReceived (no money has actually moved yet); the order still
     * moves out of Pending so fulfillment can start, same as the
     * auto-capture path today.
     */
    public function processAirwallexAuthorization(array $webhookData): void
    {
        $eventId = $webhookData['id'] ?? null;
        $paymentIntentId = $webhookData['data']['object']['id'] ?? null;

        if (!$eventId || !$paymentIntentId) {
            Log::error('Invalid Airwallex requires_capture webhook data', ['data' => $webhookData]);
            throw new \RuntimeException('Invalid webhook data');
        }

        $payment = Payment::where('transaction_id', $paymentIntentId)
            ->where('gateway', PaymentGateway::Airwallex)
            ->first();

        if (!$payment) {
            Log::error('Payment not found for requires_capture webhook', ['payment_intent_id' => $paymentIntentId]);
            throw new \RuntimeException('Payment not found');
        }

        DB::transaction(function () use ($payment, $paymentIntentId, $webhookData, $eventId) {
            $payment->update([
                'status' => PaymentTransactionStatus::Authorized,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $webhookData]),
            ]);

            $order = $payment->order;

            // Same guard as processSuccessfulPayment() below — only advance a
            // still-Pending order. A requires_capture retry delivery landing
            // after the order has already moved on must not re-trigger this.
            if ($order->status === \App\Enums\OrderStatus::Pending) {
                $this->orderService->transitionStatus(
                    $order,
                    \App\Enums\OrderStatus::Processing,
                    'Payment authorized (funds held) via Airwallex webhook',
                    null,
                    notifyCustomer: false,
                );
            }

            dispatch(new SendOrderConfirmationEmail($order));

            Log::info('Airwallex payment authorized — funds held, awaiting capture', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'event_id' => $eventId,
            ]);
        });
    }

    /**
     * Create a Paysera order + payment link for an order.
     *
     * Checkout Modern is a two-step flow (unlike Airwallex's single payment
     * intent): POST /orders first, then POST /payment-links against the
     * returned order_id. Returns the payment_URL the customer is redirected
     * to (Paysera hosts the actual card form — no client-side SDK/iframe
     * needed, unlike Airwallex's Drop-in element).
     */
    public function createPayseraPaymentLink(Order $order): array
    {
        $clientId = $this->settings->get('payment.paysera_client_id', '');
        $clientSecret = $this->settings->get('payment.paysera_client_secret', '');

        if (empty($clientId) || empty($clientSecret)) {
            throw new \RuntimeException('Paysera credentials not configured.');
        }

        // Paysera expects amounts in minor currency units (cents for EUR),
        // same convention as Airwallex above.
        $amountMinorUnits = (int) bcmul($order->grand_total, '100', 0);

        try {
            $token = $this->payseraAuthToken($clientId, $clientSecret);

            $orderResponse = Http::withToken($token)
                ->timeout(15)->retry(3, 1000)
                ->post(self::PAYSERA_API_BASE.'/merchant-order/integration/v1/orders', [
                    'redirect_urls' => [
                        'success_url' => route('frontend.checkout.thank-you', [
                            'lang' => app()->getLocale(),
                            'order' => $order->order_number,
                        ]),
                        'failure_url' => route('frontend.checkout.payment.failed', [
                            'lang' => app()->getLocale(),
                            'order' => $order->order_number,
                        ]),
                        'callback_url' => route('webhooks.paysera'),
                    ],
                    'purchase' => [
                        'reference' => $order->order_number,
                        'amount' => $amountMinorUnits,
                        'currency' => settings('general.currency', 'EUR'),
                    ],
                ]);

            $orderData = $orderResponse->json();

            if (!$orderResponse->successful() || !isset($orderData['order_id'])) {
                Log::error('Paysera order creation failed', [
                    'order_id' => $order->id,
                    'response' => $orderData,
                ]);
                throw new \RuntimeException('Failed to create Paysera order.');
            }

            $linkResponse = Http::withToken($token)
                ->timeout(15)->retry(3, 1000)
                ->post(self::PAYSERA_API_BASE.'/checkout-payment-link/integration/v1/payment-links', [
                    'order_id' => $orderData['order_id'],
                    'name' => 'Order #'.$order->order_number,
                    'experience' => [
                        'language' => app()->getLocale(),
                    ],
                    'purchase' => [
                        'amount' => $amountMinorUnits,
                    ],
                    'payer_information' => array_filter([
                        'email' => $order->guest_email ?? $order->user?->email,
                    ]),
                ]);

            $linkData = $linkResponse->json();

            if (!$linkResponse->successful() || !isset($linkData['payment_URL'])) {
                Log::error('Paysera payment link creation failed', [
                    'order_id' => $order->id,
                    'response' => $linkData,
                ]);
                throw new \RuntimeException('Failed to create Paysera payment link.');
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'gateway' => PaymentGateway::Paysera,
                'transaction_id' => $orderData['order_id'],
                'status' => PaymentTransactionStatus::Pending,
                'amount' => $order->grand_total,
                'gateway_response' => ['order' => $orderData, 'link' => $linkData],
            ]);

            return [
                'payment_url' => $linkData['payment_URL'],
                'order_id' => $orderData['order_id'],
                'payment_id' => $payment->id,
            ];
        } catch (\Exception $e) {
            Log::error('Paysera API error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Payment gateway error: '.$e->getMessage());
        }
    }

    /**
     * Exchange the configured Client ID / Secret for a bearer token via
     * Paysera's OAuth2 client-credentials flow. Tokens last 3600s per
     * Paysera's docs (no refresh token issued); cached for 55 minutes to
     * stay safely inside that expiry while avoiding a token round trip on
     * every checkout.
     */
    private function payseraAuthToken(string $clientId, string $clientSecret): string
    {
        $cacheKey = 'paysera_auth_token:'.md5($clientId.$clientSecret);

        return Cache::remember($cacheKey, now()->addMinutes(55), function () use ($clientId, $clientSecret) {
            $response = Http::asForm()->timeout(15)->post(self::PAYSERA_TOKEN_URL, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (!$response->successful() || !$response->json('access_token')) {
                throw new \RuntimeException('Paysera authentication failed: HTTP '.$response->status());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Verify a Paysera webhook signature.
     *
     * Best-effort HMAC-SHA256-over-raw-payload implementation per Paysera's
     * published Checkout Modern guide — unlike Airwallex's documented
     * timestamp+payload scheme, Paysera's public docs don't spell out the
     * exact signed-string format or header name beyond an example, so this
     * MUST be re-verified against a real callback delivery (header name,
     * signing key, exact algorithm) once live/sandbox credentials are on
     * hand, before this gateway is trusted with real traffic.
     */
    public function verifyPayseraWebhookSignature(string $payload, ?string $signature): bool
    {
        $webhookSecret = $this->settings->get('payment.paysera_webhook_secret', '');

        if (empty($webhookSecret) || empty($signature)) {
            Log::warning('Paysera webhook secret not configured or signature missing');

            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Check idempotency of a Paysera webhook delivery.
     *
     * Keyed by order_id + status rather than order_id alone — a single
     * order legitimately receives multiple callbacks over its lifecycle
     * (e.g. pending_payment, then paid), and deduping on order_id alone
     * would silently drop the second, real status transition.
     */
    public function isDuplicatePayseraEvent(string $orderId, ?string $status): bool
    {
        $cacheKey = 'paysera_webhook_'.$orderId.':'.($status ?? 'unknown');
        $ttl = (int) settings('payment.webhook_cache_days', 7) * 24 * 60;

        return !Cache::add($cacheKey, true, $ttl);
    }

    /**
     * Process a successful (status: paid) Paysera webhook.
     */
    public function processSuccessfulPayseraPayment(array $webhookData): void
    {
        $payseraOrderId = $webhookData['order_id'] ?? null;

        if (!$payseraOrderId) {
            Log::error('Invalid Paysera webhook data', ['data' => $webhookData]);
            throw new \RuntimeException('Invalid webhook data');
        }

        $payment = Payment::where('transaction_id', $payseraOrderId)
            ->where('gateway', PaymentGateway::Paysera)
            ->first();

        if (!$payment) {
            Log::error('Payment not found for Paysera webhook', ['paysera_order_id' => $payseraOrderId]);
            throw new \RuntimeException('Payment not found');
        }

        DB::transaction(function () use ($payment, $payseraOrderId, $webhookData) {
            $payment->update([
                'status' => PaymentTransactionStatus::Captured,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $webhookData]),
            ]);

            $order = $payment->order;
            $order->update([
                'payment_status' => \App\Enums\PaymentStatus::Paid,
                'payment_reference' => $payseraOrderId,
            ]);

            $this->orderService->transitionStatus(
                $order,
                \App\Enums\OrderStatus::Processing,
                'Payment confirmed via Paysera webhook',
                null,
                notifyCustomer: false,
            );

            dispatch(new SendOrderConfirmationEmail($order));

            \App\Events\PaymentReceived::dispatch($order, $payment);

            Log::info('Paysera payment processed successfully', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);
        });
    }

    /**
     * Process a failed/canceled Paysera webhook.
     */
    public function processFailedPayseraPayment(array $webhookData): void
    {
        $payseraOrderId = $webhookData['order_id'] ?? null;
        if (!$payseraOrderId) {
            return;
        }

        $payment = Payment::where('transaction_id', $payseraOrderId)
            ->where('gateway', PaymentGateway::Paysera)
            ->first();

        if ($payment) {
            DB::transaction(function () use ($payment, $webhookData) {
                $payment->update([
                    'status' => PaymentTransactionStatus::Failed,
                    'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $webhookData]),
                ]);

                $order = $payment->order;
                $order->update([
                    'payment_status' => \App\Enums\PaymentStatus::Failed,
                ]);
            });

            Log::warning('Paysera payment failed via webhook', [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Generate bank transfer details for an order.
     *
     * Returns IBAN, BIC, reference, and amount for display.
     */
    public function getBankTransferDetails(Order $order): array
    {
        $bankName = $this->settings->get('payment.bank_name', '');
        $iban = $this->settings->get('payment.bank_iban', '');
        $bic = $this->settings->get('payment.bank_bic', '');
        $accountHolder = $this->settings->get('payment.bank_account_holder', '');

        if (empty($iban) || empty($bic)) {
            throw new \RuntimeException('Bank transfer details not configured.');
        }

        // Generate a unique reference for this order
        $reference = $this->settings->get('payment.bank_reference_prefix', 'OEM') . '-' . $order->order_number;

        // Create payment record
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::BankTransfer,
            'transaction_id' => $reference,
            'status' => PaymentTransactionStatus::Pending,
            'amount' => $order->grand_total,
            'gateway_response' => null,
        ]);

        return [
            'bank_name' => $bankName,
            'iban' => $iban,
            'bic' => $bic,
            'account_holder' => $accountHolder,
            'reference' => $reference,
            'amount' => $order->grand_total,
            'currency' => settings('general.currency', 'EUR'),
            'payment_id' => $payment->id,
            'expiry_hours' => $this->settings->get('orders.bank_transfer_expiry_hours', 48),
        ];
    }

    /**
     * Verify Airwallex webhook signature.
     *
     * @param string $payload Raw request body
     * @param string $signature Signature from X-Signature header
     * @param int $timestamp Timestamp from X-Timestamp header
     * @return bool True if valid
     */
    public function verifyWebhookSignature(string $payload, string $signature, int $timestamp): bool
    {
        $webhookSecret = $this->settings->get('payment.airwallex_webhook_secret', '');

        if (empty($webhookSecret)) {
            Log::warning('Airwallex webhook secret not configured');
            return false;
        }

        // Verify timestamp within 5 minutes
        $now = time();
        $tolerance = (int) settings('payment.webhook_tolerance_seconds', 300);
        if (abs($now - $timestamp) > $tolerance) {
            Log::warning('Airwallex webhook timestamp expired', [
                'timestamp' => $timestamp,
                'now' => $now,
            ]);
            return false;
        }

        // Compute expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Check idempotency of a webhook event.
     *
     * Uses cache to prevent duplicate processing of the same event_id.
     *
     * @param string $eventId Airwallex event ID
     * @return bool True if event has already been processed
     */
    public function isDuplicateEvent(string $eventId): bool
    {
        $cacheKey = "airwallex_webhook_{$eventId}";
        $ttl = (int) settings('payment.webhook_cache_days', 7) * 24 * 60;

        return ! Cache::add($cacheKey, true, $ttl);
    }

    /**
     * Mark a webhook event as processed.
     *
     * Uses Cache::add() for atomic idempotency — if isDuplicateEvent() already
     * added the key, this is a no-op.
     */
    public function markEventProcessed(string $eventId): void
    {
        // Already handled atomically by isDuplicateEvent() via Cache::add().
        // Left as a no-op for call-site compatibility.
    }

    /**
     * Process a successful payment webhook.
     *
     * Updates order and payment statuses.
     * Should be dispatched to the 'critical' queue.
     */
    public function processSuccessfulPayment(array $webhookData): void
    {
        $eventId = $webhookData['id'] ?? null;
        $paymentIntentId = $webhookData['data']['object']['id'] ?? null;

        if (!$eventId || !$paymentIntentId) {
            Log::error('Invalid Airwallex webhook data', ['data' => $webhookData]);
            throw new \RuntimeException('Invalid webhook data');
        }

        // Find payment by transaction_id (payment_intent_id)
        $payment = Payment::where('transaction_id', $paymentIntentId)
            ->where('gateway', PaymentGateway::Airwallex)
            ->first();

        if (!$payment) {
            Log::error('Payment not found for webhook', ['payment_intent_id' => $paymentIntentId]);
            throw new \RuntimeException('Payment not found');
        }

        DB::transaction(function () use ($payment, $paymentIntentId, $webhookData, $eventId) {
            // Update payment status
            $payment->update([
                'status' => PaymentTransactionStatus::Captured,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $webhookData]),
            ]);

            // Update order
            $order = $payment->order;
            $order->update([
                'payment_status' => \App\Enums\PaymentStatus::Paid,
                'payment_reference' => $paymentIntentId,
            ]);

            // Only a still-Pending order needs to advance here. With manual
            // capture, this succeeded event can land long after the order was
            // already authorized-and-moved-to-Processing (see
            // processAirwallexAuthorization()) — possibly even after it
            // shipped, since capture is triggered on the Shipped transition.
            // Forcing Processing again on an order that's already Shipped
            // would violate OrderService's transition matrix and throw,
            // failing this webhook job despite the capture having succeeded.
            if ($order->status === \App\Enums\OrderStatus::Pending) {
                $this->orderService->transitionStatus(
                    $order,
                    \App\Enums\OrderStatus::Processing,
                    'Payment confirmed via Airwallex webhook',
                    null,
                    notifyCustomer: false,
                );
            }

            dispatch(new SendOrderConfirmationEmail($order));

            \App\Events\PaymentReceived::dispatch($order, $payment);

            Log::info('Payment processed successfully', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'event_id' => $eventId,
            ]);
        });
    }

    /**
     * Process a failed payment webhook.
     */
    public function processFailedPayment(array $webhookData): void
    {
        $paymentIntentId = $webhookData['data']['object']['id'] ?? null;
        if (!$paymentIntentId) {
            return;
        }

        $payment = Payment::where('transaction_id', $paymentIntentId)
            ->where('gateway', PaymentGateway::Airwallex)
            ->first();

        if ($payment) {
            DB::transaction(function () use ($payment, $webhookData) {
                $payment->update([
                    'status' => PaymentTransactionStatus::Failed,
                    'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $webhookData]),
                ]);

                $order = $payment->order;
                $order->update([
                    'payment_status' => \App\Enums\PaymentStatus::Failed,
                ]);
            });

            Log::warning('Payment failed via webhook', [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Manually confirm a bank transfer payment (admin action).
     */
    public function confirmBankTransferPayment(Payment $payment, string $referenceNote = '', ?int $adminId = null): void
    {
        if ($payment->gateway !== PaymentGateway::BankTransfer) {
            throw new \RuntimeException('Payment is not a bank transfer.');
        }

        DB::transaction(function () use ($payment, $referenceNote, $adminId) {
            $payment->update([
                'status' => PaymentTransactionStatus::Captured,
            ]);

            $order = $payment->order;
            $order->update([
                'payment_status' => \App\Enums\PaymentStatus::Paid,
                'payment_reference' => $referenceNote ?: $payment->transaction_id,
            ]);

            $this->orderService->transitionStatus(
                $order,
                \App\Enums\OrderStatus::Processing,
                'Bank transfer confirmed manually' . ($referenceNote ? ": {$referenceNote}" : ''),
                $adminId,
                notifyCustomer: false,
            );

            dispatch(new SendOrderConfirmationEmail($order));

            Log::info('Bank transfer payment confirmed', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);
        });
    }
}