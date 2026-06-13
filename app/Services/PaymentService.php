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

    public function __construct(
        private SettingsService $settings
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

        $payload = [
            'request_id' => Str::uuid()->toString(),
            'amount' => (string) $amountCents,
            'currency' => settings('store.currency', 'EUR'),
            'merchant_order_id' => $order->order_number,
            'customer' => [
                'email' => $order->guest_email ?? $order->user->email,
            ],
            'return_url' => route('frontend.checkout.thank-you', [
                'lang' => app()->getLocale(),
                'order' => $order->order_number,
            ]),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
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
        $reference = settings('payment.reference_prefix', 'OEM') . '-' . $order->order_number;

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
            'currency' => settings('store.currency', 'EUR'),
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
            $oldStatus = $order->status;
            $order->update([
                'payment_status' => \App\Enums\PaymentStatus::Paid,
                'status' => \App\Enums\OrderStatus::Processing,
                'payment_reference' => $paymentIntentId,
            ]);

            // Log status change
            \App\Models\OrderStatusHistory::create([
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => \App\Enums\OrderStatus::Processing,
                'note' => 'Payment confirmed via Airwallex webhook',
            ]);

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
    public function confirmBankTransferPayment(Payment $payment, string $referenceNote = ''): void
    {
        if ($payment->gateway !== PaymentGateway::BankTransfer) {
            throw new \RuntimeException('Payment is not a bank transfer.');
        }

        DB::transaction(function () use ($payment, $referenceNote) {
            $payment->update([
                'status' => PaymentTransactionStatus::Captured,
            ]);

            $order = $payment->order;
            $oldStatus = $order->status;
            $order->update([
                'payment_status' => \App\Enums\PaymentStatus::Paid,
                'status' => \App\Enums\OrderStatus::Processing,
                'payment_reference' => $referenceNote ?: $payment->transaction_id,
            ]);

            \App\Models\OrderStatusHistory::create([
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => \App\Enums\OrderStatus::Processing,
                'note' => 'Bank transfer confirmed manually' . ($referenceNote ? ": {$referenceNote}" : ''),
            ]);

            dispatch(new SendOrderConfirmationEmail($order));

            Log::info('Bank transfer payment confirmed', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);
        });
    }
}