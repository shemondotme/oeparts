<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAirwallexWebhook;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * WebhookController — handles incoming Airwallex webhooks.
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
        // Get raw payload before any parsing
        $payload = $request->getContent();
        $signature = $request->header('X-Signature');
        $timestamp = (int) $request->header('X-Timestamp');

        Log::debug('Airwallex webhook received', [
            'event_type' => $request->input('type'),
            'timestamp' => $timestamp,
            'signature_present' => !empty($signature),
        ]);

        // 1. Verify signature
        if (!$this->paymentService->verifyWebhookSignature($payload, $signature, $timestamp)) {
            Log::warning('Airwallex webhook signature verification failed', [
                'timestamp' => $timestamp,
                'signature' => substr($signature ?? '', 0, 8) . '***',
            ]);
            return response('Invalid signature', 401);
        }

        // 2. Parse JSON payload
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Airwallex webhook invalid JSON', [
                'error' => json_last_error_msg(),
            ]);
            return response('Invalid JSON', 400);
        }

        $eventId = $data['id'] ?? null;
        $eventType = $data['type'] ?? null;

        if (!$eventId || !$eventType) {
            Log::warning('Airwallex webhook missing required fields', ['data' => $data]);
            return response('Missing required fields', 400);
        }

        // 3. Check idempotency
        if ($this->paymentService->isDuplicateEvent($eventId)) {
            Log::info('Airwallex webhook duplicate event ignored', ['event_id' => $eventId]);
            return response('Event already processed', 200);
        }

        // 4. Mark event as processed (to prevent concurrent processing)
        $this->paymentService->markEventProcessed($eventId);

        // 5. Dispatch to queue for processing
        ProcessAirwallexWebhook::dispatch($data)->onQueue('critical');

        Log::info('Airwallex webhook accepted and queued', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        return response('Webhook accepted', 200);
    }

    /**
     * Handle bank transfer confirmation (admin webhook simulation).
     * This endpoint is for internal use when admin manually confirms a bank transfer.
     *
     * POST /webhooks/bank-transfer-confirm
     */
    public function handleBankTransferConfirm(Request $request): Response
    {
        $rateKey = 'bank-transfer:' . ($request->input('payment_id') ?? $request->ip());
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return response()->json(['success' => false, 'message' => 'Too many attempts'], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $apiKey = $request->header('X-Webhook-Key');
        $expectedKey = settings('payment.webhook_secret', '');

        if ($apiKey !== $expectedKey) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = \Validator::make($request->all(), [
            'payment_id' => 'required|exists:payments,id',
            'reference_note' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $payment = \App\Models\Payment::find($request->input('payment_id'));

        if ($payment->gateway !== \App\Enums\PaymentGateway::BankTransfer) {
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