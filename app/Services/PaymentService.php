<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Events\PaymentReceived;
use App\Jobs\NotifyAdminsOfPaymentDispute;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Payments\PayseraWebhookEvent;
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

        // The customer's payment page can be loaded more than once (reload,
        // back button, a second tab) — hand back the intent already created
        // for this order instead of minting a new Airwallex intent AND a new
        // Payment row every time. Otherwise the order accumulates orphaned
        // Pending payments and `$order->payment` stops pointing at the one
        // actually being paid.
        if ($reusable = $this->reusableAirwallexIntent($order)) {
            return $reusable;
        }

        $manualCaptureEnabled = filter_var(
            $this->settings->get('payment.airwallex_manual_capture_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $payload = [
            'request_id' => Str::uuid()->toString(),
            // MAJOR units (108.87 for EUR 108.87), NOT cents — Airwallex's
            // PaymentIntent guide: "The amount to charge specified in major
            // units as defined by ISO 4217. For example, $9.99 is
            // represented as 9.99." Sending cents charged 100x the order.
            'amount' => $this->airwallexAmount($order->grand_total),
            'currency' => settings('general.currency', 'EUR'),
            'merchant_order_id' => $order->order_number,
            'customer' => [
                'email' => $order->guest_email ?? $order->user->email,
            ],
            // The waiting page, not the thank-you page: it only forwards to
            // thank-you once the webhook has actually confirmed payment, so a
            // customer coming back from a redirect-based flow whose payment
            // failed or is still pending isn't told "thank you".
            'return_url' => route('frontend.checkout.payment.return', [
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

            if (! $response->successful() || ! isset($data['client_secret'])) {
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
            throw new \RuntimeException('Payment gateway error: '.$e->getMessage());
        }
    }

    /**
     * Order total as the JSON number Airwallex expects: a decimal in major
     * currency units, rounded to 2 places via bcmath (no float arithmetic on
     * money — the cast happens only at the very end, for serialization).
     */
    public function airwallexAmount(string|int|float $total): float
    {
        return (float) bcadd((string) $total, '0', 2);
    }

    /**
     * A still-usable intent for this order, if one exists: Pending, same
     * amount, created inside the client_secret's validity window (Airwallex:
     * "The secret is valid for 60 minutes from the moment the PaymentIntent
     * is created", and cannot be refreshed — 50 leaves margin).
     *
     * @return array{client_secret: string, payment_intent_id: string, payment_id: int}|null
     */
    private function reusableAirwallexIntent(Order $order): ?array
    {
        /** @var Payment|null $payment */
        $payment = $order->payments()
            ->where('gateway', PaymentGateway::Airwallex)
            ->where('status', PaymentTransactionStatus::Pending)
            ->where('created_at', '>=', now()->subMinutes(50))
            ->latest('id')
            ->first();

        $clientSecret = $payment?->gateway_response['client_secret'] ?? null;

        if (! $payment || ! $clientSecret || ! $payment->transaction_id
            || bccomp((string) $payment->amount, (string) $order->grand_total, 2) !== 0) {
            return null;
        }

        return [
            'client_secret' => $clientSecret,
            'payment_intent_id' => $payment->transaction_id,
            'payment_id' => $payment->id,
        ];
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
        $cacheKey = 'airwallex_auth_token:'.md5($baseUrl.$clientId);

        return Cache::remember($cacheKey, now()->addMinutes(25), function () use ($baseUrl, $clientId, $apiKey) {
            $response = Http::withHeaders([
                'x-client-id' => $clientId,
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post("{$baseUrl}/authentication/login", (object) []);

            if (! $response->successful() || ! $response->json('token')) {
                throw new \RuntimeException('Airwallex authentication failed: HTTP '.$response->status());
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

            // request_id is REQUIRED by Airwallex's capture endpoint (amount is
            // optional — omitted, it captures the full authorized amount). One
            // id per call, generated before the retry loop, so ->retry()'s
            // re-sends are the same idempotent request, not new captures.
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ])->timeout(15)->retry(3, 1000)->post(
                "{$baseUrl}/pa/payment_intents/{$payment->transaction_id}/capture",
                ['request_id' => Str::uuid()->toString()]
            );

            if (! $response->successful()) {
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

        if (! $eventId || ! $paymentIntentId) {
            Log::error('Invalid Airwallex requires_capture webhook data', ['data' => $webhookData]);
            throw new \RuntimeException('Invalid webhook data');
        }

        $payment = Payment::where('transaction_id', $paymentIntentId)
            ->where('gateway', PaymentGateway::Airwallex)
            ->first();

        if (! $payment) {
            Log::error('Payment not found for requires_capture webhook', ['payment_intent_id' => $paymentIntentId]);
            throw new \RuntimeException('Payment not found');
        }

        // Airwallex does not guarantee delivery order. A requires_capture that
        // arrives after the payment already succeeded is history, not news —
        // applying it would downgrade a Captured payment back to Authorized.
        if ($payment->status === PaymentTransactionStatus::Captured) {
            Log::info('Airwallex requires_capture arrived after capture — ignored (out-of-order delivery)', [
                'payment_id' => $payment->id,
                'event_id' => $eventId,
            ]);

            return;
        }

        DB::transaction(function () use ($payment, $webhookData, $eventId) {
            $payment->update([
                'status' => PaymentTransactionStatus::Authorized,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $webhookData]),
            ]);

            $order = $payment->order;

            // Same guard as processSuccessfulPayment() below — only advance a
            // still-Pending order. A requires_capture retry delivery landing
            // after the order has already moved on must not re-trigger this.
            if ($order->status === OrderStatus::Pending) {
                $this->orderService->transitionStatus(
                    $order,
                    OrderStatus::Processing,
                    'Payment authorized (funds held) via Airwallex webhook',
                    null,
                    notifyCustomer: false,
                );

                // Only send once, on the order's first authorization — a
                // requires_capture retry delivery landing after the order has
                // already moved on must not re-send the confirmation email
                // (processSuccessfulPayment() sends its own later, at capture,
                // guarded the same way).
                dispatch(new SendOrderConfirmationEmail($order));
            }

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

            // Paysera's own docs are inconsistent about the identifier's key:
            // the response schema lists `id` (UUID) while their PHP sample reads
            // `$order['order_id']`. Accept either rather than fail every payment
            // on whichever one the API really returns.
            $payseraOrderId = is_array($orderData) ? ($orderData['order_id'] ?? $orderData['id'] ?? null) : null;

            if (! $orderResponse->successful() || ! $payseraOrderId) {
                Log::error('Paysera order creation failed', [
                    'order_id' => $order->id,
                    'response' => $orderData,
                ]);
                throw new \RuntimeException('Failed to create Paysera order.');
            }

            $linkResponse = Http::withToken($token)
                ->timeout(15)->retry(3, 1000)
                ->post(self::PAYSERA_API_BASE.'/checkout-payment-link/integration/v1/payment-links', [
                    'order_id' => $payseraOrderId,
                    'name' => 'Order #'.$order->order_number,
                    'experience' => [
                        'language' => $this->payseraLanguage(app()->getLocale()),
                    ],
                    'purchase' => [
                        'amount' => $amountMinorUnits,
                    ],
                    'payer_information' => array_filter([
                        'email' => $order->guest_email ?? $order->user?->email,
                    ]),
                ]);

            $linkData = $linkResponse->json();

            if (! $linkResponse->successful() || ! isset($linkData['payment_URL'])) {
                Log::error('Paysera payment link creation failed', [
                    'order_id' => $order->id,
                    'response' => $linkData,
                ]);
                throw new \RuntimeException('Failed to create Paysera payment link.');
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'gateway' => PaymentGateway::Paysera,
                'transaction_id' => $payseraOrderId,
                'status' => PaymentTransactionStatus::Pending,
                'amount' => $order->grand_total,
                'gateway_response' => ['order' => $orderData, 'link' => $linkData],
            ]);

            return [
                'payment_url' => $linkData['payment_URL'],
                'order_id' => $payseraOrderId,
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
     * `experience.language` is REQUIRED on a Paysera payment link, and their
     * docs only document ISO 639-1 codes with "en" and "lt" as the examples —
     * they never enumerate the supported set. This site also serves de/fr/es;
     * sending one Paysera doesn't recognise risks a 422 that blocks the whole
     * payment, while falling back to English merely shows the hosted page in
     * English. Extend the list once a code is confirmed against Paysera.
     */
    private function payseraLanguage(string $locale): string
    {
        return in_array($locale, ['en', 'lt'], true) ? $locale : 'en';
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

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new \RuntimeException('Paysera authentication failed: HTTP '.$response->status());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Verify a Paysera callback signature, per Paysera's "Webhooks" guide:
     * `X-Paysera-Signature` is the hex HMAC-SHA256 of the RAW request body,
     * keyed with the OAuth **client secret** — the same secret used to obtain
     * API tokens. There is no separate webhook secret; an earlier version read
     * one from its own setting, so a merchant following Paysera's docs could
     * never have verified a genuine callback.
     */
    public function verifyPayseraWebhookSignature(string $payload, ?string $signature, ?string $algorithm = null): bool
    {
        $secret = $this->settings->get('payment.paysera_client_secret', '');

        if (empty($secret) || empty($signature)) {
            Log::warning('Paysera client secret not configured or callback signature missing');

            return false;
        }

        // X-Paysera-Signature-Alg is documented as HMAC-SHA256. Refuse anything
        // else rather than compare against a digest of the wrong algorithm.
        if ($algorithm !== null && $algorithm !== '' && strcasecmp($algorithm, 'HMAC-SHA256') !== 0) {
            Log::warning('Paysera callback signed with an unsupported algorithm', ['algorithm' => $algorithm]);

            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), strtolower($signature));
    }

    /**
     * Idempotency for a Paysera callback, keyed on a hash of the raw body.
     *
     * Paysera retries up to 4 times (immediately, ~1h, ~6h, ~31h) when it
     * doesn't get a 2xx. Its docs suggest X-Paysera-Callback-Id, but describe
     * that id as identifying a single delivery *attempt* — if it changes per
     * retry it can't recognise one. A retry carries the identical event
     * snapshot, while a genuinely new event differs in status, amount_paid or
     * timestamp, so the body hash identifies "the same event" either way.
     * (Handlers are also state-guarded, so a duplicate that slips through is
     * harmless.)
     */
    public function isDuplicatePayseraEvent(string $payload): bool
    {
        // Cache::add()'s integer $ttl is seconds, not minutes.
        $ttl = (int) settings('payment.webhook_cache_days', 7) * 24 * 60 * 60;

        return ! Cache::add($this->payseraEventKey($payload), true, $ttl);
    }

    /** Forget a claimed callback whose job could not be queued, so Paysera's retry is processed. */
    public function releasePayseraEvent(string $payload): void
    {
        Cache::forget($this->payseraEventKey($payload));
    }

    private function payseraEventKey(string $payload): string
    {
        return 'paysera_webhook_'.hash('sha256', $payload);
    }

    /**
     * Process an order callback whose status is `paid` ("Order total amount
     * equals amount paid" — Paysera's fulfilment trigger).
     *
     * @param  array  $webhookData  The decoded callback body (see PayseraWebhookEvent)
     */
    public function processSuccessfulPayseraPayment(array $webhookData): void
    {
        $event = PayseraWebhookEvent::fromArray($webhookData);

        if (! $event->payseraOrderId) {
            Log::error('Invalid Paysera webhook data', ['data' => $webhookData]);
            throw new \RuntimeException('Invalid webhook data');
        }

        $payment = Payment::where('transaction_id', $event->payseraOrderId)
            ->where('gateway', PaymentGateway::Paysera)
            ->first();

        if (! $payment) {
            Log::error('Payment not found for Paysera webhook', ['paysera_order_id' => $event->payseraOrderId]);
            throw new \RuntimeException('Payment not found');
        }

        // "paid" means the *Paysera* order is fully paid — verify it is the
        // amount/currency this payment was created for before fulfilling.
        // (An order edited after its payment link was issued would otherwise
        // ship against a payment that no longer matches its total.)
        $expectedMinorUnits = (int) bcmul((string) $payment->amount, '100', 0);
        $currency = strtoupper((string) settings('general.currency', 'EUR'));

        if ($event->amountPaid !== null && $event->amountPaid < $expectedMinorUnits) {
            Log::critical('Paysera reports paid but amount_paid is below the payment amount — NOT marking paid', [
                'payment_id' => $payment->id, 'expected_minor_units' => $expectedMinorUnits, 'amount_paid' => $event->amountPaid,
            ]);
            throw new \RuntimeException('Paysera amount_paid does not cover the payment amount');
        }

        if ($event->currency !== null && strtoupper($event->currency) !== $currency) {
            Log::critical('Paysera paid callback is in an unexpected currency — NOT marking paid', [
                'payment_id' => $payment->id, 'expected' => $currency, 'received' => $event->currency,
            ]);
            throw new \RuntimeException('Paysera currency does not match the order currency');
        }

        /** @var Order $paymentOrder */
        $paymentOrder = $payment->order;

        if ($event->merchantOrderId !== null && $event->merchantOrderId !== $paymentOrder->order_number) {
            Log::warning('Paysera merchant_order_id differs from our order number', [
                'payment_id' => $payment->id, 'merchant_order_id' => $event->merchantOrderId,
            ]);
        }

        DB::transaction(function () use ($payment, $event, $webhookData) {
            $payment->update([
                'status' => PaymentTransactionStatus::Captured,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $webhookData]),
            ]);

            /** @var Order $order */
            $order = $payment->order;
            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'payment_reference' => $event->payseraOrderId,
            ]);

            // Only a still-Pending order needs to advance here — same guard as
            // processSuccessfulPayment() / processAirwallexAuthorization() use
            // for Airwallex. A retried "paid" callback landing after the order
            // already moved on (the idempotency entry expired, or Paysera
            // redelivers because our 200 was lost in transit) must not attempt
            // an invalid transition and throw, nor re-send the confirmation.
            if ($order->status === OrderStatus::Pending) {
                $this->orderService->transitionStatus(
                    $order,
                    OrderStatus::Processing,
                    'Payment confirmed via Paysera webhook',
                    null,
                    notifyCustomer: false,
                );

                dispatch(new SendOrderConfirmationEmail($order));
            }

            PaymentReceived::dispatch($order, $payment);

            Log::info('Paysera payment processed successfully', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);
        });
    }

    /**
     * Process an order callback whose status is `canceled` ("Order was
     * canceled before anything was paid"). Never overrides a payment that
     * already succeeded — callbacks can be retried out of order.
     */
    public function processFailedPayseraPayment(array $webhookData): void
    {
        $event = PayseraWebhookEvent::fromArray($webhookData);
        if (! $event->payseraOrderId) {
            return;
        }

        $payment = Payment::where('transaction_id', $event->payseraOrderId)
            ->where('gateway', PaymentGateway::Paysera)
            ->first();

        if (! $payment) {
            return;
        }

        if ($payment->status === PaymentTransactionStatus::Captured) {
            Log::warning('Paysera canceled callback ignored — payment already captured (out-of-order delivery)', [
                'payment_id' => $payment->id,
            ]);

            return;
        }

        DB::transaction(function () use ($payment, $webhookData) {
            $payment->update([
                'status' => PaymentTransactionStatus::Failed,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $webhookData]),
            ]);

            $payment->order->update([
                'payment_status' => PaymentStatus::Failed,
            ]);
        });

        Log::warning('Paysera payment failed via webhook', [
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * A Paysera payment reached the `chargeback` status ("Dispute
     * initiated"). Alert-only, the same policy as Airwallex disputes: admins
     * are notified, nothing about the order or payment changes automatically.
     */
    public function processPayseraChargeback(array $webhookData): void
    {
        $event = PayseraWebhookEvent::fromArray($webhookData);

        $payment = $event->payseraOrderId
            ? Payment::where('transaction_id', $event->payseraOrderId)->where('gateway', PaymentGateway::Paysera)->first()
            : null;

        /** @var Order|null $order */
        $order = $payment?->order;
        $paymentPayload = is_array($webhookData['payment'] ?? null) ? $webhookData['payment'] : [];

        Log::warning('Paysera chargeback received', [
            'paysera_order_id' => $event->payseraOrderId,
            'payment_id' => $event->paymentId,
            'order_id' => $order?->id,
        ]);

        NotifyAdminsOfPaymentDispute::dispatch(
            eventType: 'paysera.payment.chargeback',
            orderId: $order?->id,
            orderNumber: $order?->order_number,
            disputeId: $event->paymentId,
            status: $event->paymentStatus,
            stage: null,
            // Paysera amounts are integer minor units — show it as a decimal.
            amount: isset($paymentPayload['amount']) && is_numeric($paymentPayload['amount'])
                ? bcdiv((string) $paymentPayload['amount'], '100', 2)
                : null,
            currency: $event->currency,
            reason: null,
        );
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
        $reference = $this->settings->get('payment.bank_reference_prefix', 'OEM').'-'.$order->order_number;

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
     * Verify an Airwallex webhook signature, exactly as their "Listen for
     * webhook events" guide specifies:
     *
     *  - headers are `x-timestamp` and `x-signature`;
     *  - value_to_digest = the x-timestamp string CONCATENATED DIRECTLY with
     *    the raw request body — no separator (an earlier version inserted a
     *    "." between them, Stripe-style, so no genuine event could ever
     *    verify);
     *  - the signature is the HMAC-SHA256 hex digest of that string, keyed
     *    with the webhook secret;
     *  - x-timestamp is a Unix timestamp in MILLISECONDS ("1357872222592"),
     *    not seconds — comparing it against time() made every event look
     *    decades stale.
     *
     * The timestamp is used exactly as received in the digest (never
     * re-formatted through an int), and the freshness window is applied
     * after the signature matches.
     *
     * @param  string  $payload  Raw request body, unmodified
     * @param  string|null  $signature  x-signature header
     * @param  string|null  $timestamp  x-timestamp header (epoch milliseconds)
     */
    public function verifyWebhookSignature(string $payload, ?string $signature, ?string $timestamp): bool
    {
        $webhookSecret = $this->settings->get('payment.airwallex_webhook_secret', '');

        if (empty($webhookSecret)) {
            Log::warning('Airwallex webhook secret not configured');

            return false;
        }

        if ($signature === null || $signature === '' || $timestamp === null || ! ctype_digit($timestamp)) {
            Log::warning('Airwallex webhook missing or malformed signature/timestamp header');

            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.$payload, $webhookSecret);

        if (! hash_equals($expectedSignature, strtolower($signature))) {
            return false;
        }

        // Freshness: the docs mandate milliseconds; treat an implausibly
        // small value as seconds so a future format change fails open to
        // "still verified" rather than rejecting every event again.
        $timestampMs = (int) $timestamp;
        if ($timestampMs < 100_000_000_000) {
            $timestampMs *= 1000;
        }

        $nowMs = (int) round(microtime(true) * 1000);
        $toleranceMs = (int) settings('payment.webhook_tolerance_seconds', 300) * 1000;

        if (abs($nowMs - $timestampMs) > $toleranceMs) {
            Log::warning('Airwallex webhook timestamp outside tolerance', [
                'timestamp_ms' => $timestampMs,
                'now_ms' => $nowMs,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Check idempotency of a webhook event.
     *
     * Uses cache to prevent duplicate processing of the same event_id — the
     * event `id` is unchanged across retries (Airwallex retries with
     * exponential back-off for about three days, and may deliver the same
     * event more than once).
     *
     * @param  string  $eventId  Airwallex event ID
     * @return bool True if event has already been processed
     */
    public function isDuplicateEvent(string $eventId): bool
    {
        // Cache::add()'s integer $ttl is SECONDS. This used to be days*24*60
        // (minutes), i.e. 7 days configured, ~2.8 hours actually enforced —
        // far short of the ~3-day retry window it has to cover.
        $ttl = (int) settings('payment.webhook_cache_days', 7) * 24 * 60 * 60;

        return ! Cache::add("airwallex_webhook_{$eventId}", true, $ttl);
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
     * Forget a claimed event so the gateway's retry is processed instead of
     * being acknowledged as a duplicate. Called when queueing the job failed
     * AFTER the event was claimed — otherwise a transient queue outage would
     * turn Airwallex's retry into "already processed" and lose the payment.
     */
    public function releaseEvent(string $eventId): void
    {
        Cache::forget("airwallex_webhook_{$eventId}");
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

        if (! $eventId || ! $paymentIntentId) {
            Log::error('Invalid Airwallex webhook data', ['data' => $webhookData]);
            throw new \RuntimeException('Invalid webhook data');
        }

        // Find payment by transaction_id (payment_intent_id)
        $payment = Payment::where('transaction_id', $paymentIntentId)
            ->where('gateway', PaymentGateway::Airwallex)
            ->first();

        if (! $payment) {
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
                'payment_status' => PaymentStatus::Paid,
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
            if ($order->status === OrderStatus::Pending) {
                $this->orderService->transitionStatus(
                    $order,
                    OrderStatus::Processing,
                    'Payment confirmed via Airwallex webhook',
                    null,
                    notifyCustomer: false,
                );

                // Guarded the same way as the transition above: on the plain
                // auto-capture path this is the order's first (and only)
                // success event, so it still always sends here. With manual
                // capture enabled, processAirwallexAuthorization() already
                // sent this at authorization time — don't send it again when
                // this later succeeded/capture event lands.
                dispatch(new SendOrderConfirmationEmail($order));
            }

            PaymentReceived::dispatch($order, $payment);

            Log::info('Payment processed successfully', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'event_id' => $eventId,
            ]);
        });
    }

    /**
     * Process a payment_intent.payment_failed webhook — "a PaymentAttempt on
     * this PaymentIntent has failed" (there is no payment_intent.failed
     * event). The intent itself is not terminal: the customer can retry.
     *
     * Never overrides a payment that already succeeded or is holding
     * authorized funds: events can arrive out of order, and a late failure
     * notice for an earlier attempt must not flip a paid order to Failed.
     */
    public function processFailedPayment(array $webhookData): void
    {
        $paymentIntentId = $webhookData['data']['object']['id'] ?? null;
        if (! $paymentIntentId) {
            return;
        }

        $payment = Payment::where('transaction_id', $paymentIntentId)
            ->where('gateway', PaymentGateway::Airwallex)
            ->first();

        if ($payment && in_array($payment->status, [PaymentTransactionStatus::Captured, PaymentTransactionStatus::Authorized], true)) {
            Log::info('Airwallex payment_failed ignored — payment already succeeded/authorized (out-of-order delivery)', [
                'payment_id' => $payment->id,
                'status' => $payment->status->value,
            ]);

            return;
        }

        if ($payment) {
            DB::transaction(function () use ($payment, $webhookData) {
                $payment->update([
                    'status' => PaymentTransactionStatus::Failed,
                    'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $webhookData]),
                ]);

                $order = $payment->order;
                $order->update([
                    'payment_status' => PaymentStatus::Failed,
                ]);
            });

            Log::warning('Payment failed via webhook', [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Process a payment_intent.cancelled webhook (British spelling — that is
     * the real event name). Cancels the order only while it is still
     * cancellable and the payment never succeeded: a cancel notice that
     * races a capture must not cancel a paid order, and an order that has
     * already shipped can't be cancelled by a gateway event.
     */
    public function processCancelledPayment(array $webhookData): void
    {
        $paymentIntentId = $webhookData['data']['object']['id'] ?? null;
        if (! $paymentIntentId) {
            return;
        }

        $payment = Payment::where('transaction_id', $paymentIntentId)
            ->where('gateway', PaymentGateway::Airwallex)
            ->first();

        if (! $payment) {
            return;
        }

        if ($payment->status === PaymentTransactionStatus::Captured) {
            Log::warning('Airwallex payment_intent.cancelled ignored — payment already captured (out-of-order delivery)', [
                'payment_id' => $payment->id,
            ]);

            return;
        }

        /** @var Order $order */
        $order = $payment->order;

        DB::transaction(function () use ($payment, $order, $webhookData) {
            $payment->update([
                'status' => PaymentTransactionStatus::Failed,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $webhookData]),
            ]);

            $order->update(['payment_status' => PaymentStatus::Failed]);
        });

        if ($order->status->canBeCancelled()) {
            $this->orderService->transitionStatus(
                $order,
                OrderStatus::Cancelled,
                'Payment canceled via Airwallex webhook',
            );
        }

        Log::warning('Payment canceled via webhook', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ]);
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
                'payment_status' => PaymentStatus::Paid,
                'payment_reference' => $referenceNote ?: $payment->transaction_id,
            ]);

            $this->orderService->transitionStatus(
                $order,
                OrderStatus::Processing,
                'Bank transfer confirmed manually'.($referenceNote ? ": {$referenceNote}" : ''),
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
