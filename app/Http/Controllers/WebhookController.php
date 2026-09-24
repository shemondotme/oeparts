<?php

namespace App\Http\Controllers;

use App\Enums\PaymentGateway;
use App\Jobs\ProcessAirwallexWebhook;
use App\Jobs\ProcessPayseraWebhook;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Payments\PayseraWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * WebhookController — handles incoming Airwallex and Paysera webhooks.
 *
 * Security:
 *  - Verify HMAC signature (X-Signature header)
 *  - Verify timestamp within 5 minutes (X-Timestamp header)
 *  - Check idempotency (prevent duplicate processing)
 *  - Dispatch to 'critical' queue for async processing
 *
 * All webhook processing is deferred to the ProcessAirwallexWebhook job.
 * This controller only validates and dispatches.
 */
class WebhookController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Handle Airwallex webhook events.
     *
     * POST /webhooks/airwallex
     */
    public function handleAirwallex(Request $request): Response
    {
        // Get raw payload before any parsing — the signature covers the exact bytes.
        $payload = $request->getContent();
        // Kept as the strings received: x-timestamp is epoch MILLISECONDS and is
        // part of the signed value, so it must not be round-tripped through an int.
        $signature = $request->header('x-signature');
        $timestamp = $request->header('x-timestamp');

        Log::debug('Airwallex webhook received', [
            'timestamp' => $timestamp,
            'signature_present' => ! empty($signature),
        ]);

        if (! $this->paymentService->verifyWebhookSignature($payload, $signature, $timestamp)) {
            Log::warning('Airwallex webhook signature verification failed', [
                'timestamp' => $timestamp,
                'signature' => substr($signature ?? '', 0, 8).'***',
            ]);

            return response('Invalid signature', 401);
        }

        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            Log::warning('Airwallex webhook invalid JSON', [
                'error' => json_last_error_msg(),
            ]);

            return response('Invalid JSON', 400);
        }

        $eventId = $data['id'] ?? null;
        // Airwallex's envelope carries the event type in `name`, not `type`.
        $eventType = ProcessAirwallexWebhook::eventName($data);

        if (! $eventId || ! $eventType) {
            Log::warning('Airwallex webhook missing required fields', ['data' => $data]);

            return response('Missing required fields', 400);
        }

        if ($this->paymentService->isDuplicateEvent($eventId)) {
            Log::info('Airwallex webhook duplicate event ignored', ['event_id' => $eventId]);

            return response('Event already processed', 200);
        }

        // Mark processed now (not after the queue job runs) to prevent a concurrent
        // duplicate delivery from also passing the idempotency check above.
        $this->paymentService->markEventProcessed($eventId);

        try {
            ProcessAirwallexWebhook::dispatch($data)->onQueue('critical');
        } catch (\Throwable $e) {
            // The event was claimed above but never queued. Release it and
            // answer non-200 so Airwallex's retry is processed, rather than
            // acknowledged as an already-handled duplicate and lost.
            $this->paymentService->releaseEvent($eventId);
            Log::error('Airwallex webhook could not be queued', ['event_id' => $eventId, 'error' => $e->getMessage()]);

            return response('Temporarily unable to process', 503);
        }

        Log::info('Airwallex webhook accepted and queued', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        return response('Webhook accepted', 200);
    }

    /**
     * Handle Paysera Checkout Modern callbacks.
     *
     * POST /webhooks/paysera
     *
     * Per Paysera's "Webhooks" guide: a JSON body (order events carry the
     * full order snapshot, payment/refund events a thin envelope — see
     * PayseraWebhookEvent), signed with `X-Paysera-Signature` (hex
     * HMAC-SHA256 of the raw body, keyed by the OAuth client secret). Any
     * 2xx acknowledges; a non-2xx is retried up to 3 more times over ~31h.
     */
    public function handlePaysera(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Paysera-Signature');

        Log::debug('Paysera webhook received', [
            'callback_id' => $request->header('X-Paysera-Callback-Id'),
            'signature_present' => ! empty($signature),
        ]);

        // Verified before parsing: the signature covers the exact bytes received.
        //
        // 403, deliberately NOT 401: Paysera treats a 401 as "stop retrying
        // this callback for good". A genuine callback that fails verification
        // almost always means OUR client secret is misconfigured — the case
        // where Paysera's retries (over ~31h) are exactly what lets the
        // merchant fix the setting and still receive the payment
        // confirmation. A forged request gains nothing from being retried.
        if (! $this->paymentService->verifyPayseraWebhookSignature(
            $payload,
            $signature,
            $request->header('X-Paysera-Signature-Alg'),
        )) {
            Log::warning('Paysera webhook signature verification failed', [
                'signature' => substr($signature ?? '', 0, 8).'***',
            ]);

            return response('Invalid signature', 403);
        }

        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            Log::warning('Paysera webhook invalid JSON', [
                'error' => json_last_error_msg(),
            ]);

            return response('Invalid JSON', 400);
        }

        $event = PayseraWebhookEvent::fromArray($data);

        // Not an event we act on (e.g. paysera.fund-distributor.* split-payment
        // events, which carry no paysera_order_id): acknowledge it so Paysera
        // doesn't retry something we will never process.
        if (! $event->payseraOrderId) {
            Log::info('Paysera webhook without an order id acknowledged and ignored', [
                'event_type' => $event->type,
                'event_name' => $event->name,
            ]);

            return response('Webhook ignored', 200);
        }

        if ($this->paymentService->isDuplicatePayseraEvent($payload)) {
            Log::info('Paysera webhook duplicate event ignored', [
                'paysera_order_id' => $event->payseraOrderId,
                'event_name' => $event->name,
            ]);

            return response('Event already processed', 200);
        }

        try {
            ProcessPayseraWebhook::dispatch($data)->onQueue('critical');
        } catch (\Throwable $e) {
            // Claimed above but never queued — release it and answer non-2xx
            // so Paysera's retry is processed instead of deduplicated away.
            $this->paymentService->releasePayseraEvent($payload);
            Log::error('Paysera webhook could not be queued', [
                'paysera_order_id' => $event->payseraOrderId,
                'error' => $e->getMessage(),
            ]);

            return response('Temporarily unable to process', 503);
        }

        Log::info('Paysera webhook accepted and queued', [
            'paysera_order_id' => $event->payseraOrderId,
            'event_type' => $event->type,
            'event_name' => $event->name,
            'status' => $event->orderStatus ?? $event->paymentStatus,
        ]);

        return response('Webhook accepted', 200);
    }

    /**
     * Handle bank transfer confirmation (admin webhook simulation).
     * This endpoint is for internal use when admin manually confirms a bank transfer.
     *
     * POST /webhooks/bank-transfer-confirm
     */
    public function handleBankTransferConfirm(Request $request): JsonResponse
    {
        $rateKey = 'bank-transfer:'.($request->input('payment_id') ?? $request->ip());
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return response()->json(['success' => false, 'message' => 'Too many attempts'], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $apiKey = (string) $request->header('X-Webhook-Key');
        $expectedKey = (string) settings('payment.webhook_secret', '');

        // hash_equals, not !==: a straight string comparison short-circuits
        // on the first mismatched byte, leaking how many leading characters
        // an attacker guessed right via response-timing — the standard
        // constant-time-compare fix for any secret/token check.
        if ($expectedKey === '' || ! hash_equals($expectedKey, $apiKey)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = \Validator::make($request->all(), [
            'payment_id' => 'required|exists:payments,id',
            'reference_note' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $payment = Payment::find($request->input('payment_id'));

        if ($payment->gateway !== PaymentGateway::BankTransfer) {
            return response()->json(['success' => false, 'message' => 'Payment is not a bank transfer'], 400);
        }

        try {
            $this->paymentService->confirmBankTransferPayment(
                $payment,
                $request->input('reference_note', '')
            );
        } catch (\Exception $e) {
            Log::error('Bank transfer confirmation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Something went wrong'], 500);
        }

        return response()->json(['success' => true, 'data' => ['payment_id' => $payment->id]]);
    }
}
