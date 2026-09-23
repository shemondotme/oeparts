<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs on the 'critical' queue for timely payment processing. Duplicate
 * webhook events are detected and skipped upstream in the controller, so
 * this job itself doesn't need its own idempotency check.
 */
class ProcessAirwallexWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private array $webhookData
    ) {
        $this->onQueue('critical');
    }

    /**
     * Get the webhook data (for testing).
     */
    public function getWebhookData(): array
    {
        return $this->webhookData;
    }

    public function handle(PaymentService $paymentService): void
    {
        $eventType = $this->webhookData['type'] ?? null;
        $eventId = $this->webhookData['id'] ?? null;

        Log::info('Processing Airwallex webhook job', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        try {
            if ($eventType !== null && str_starts_with($eventType, 'dispute.')) {
                $this->handleDispute($eventType);

                return;
            }

            match ($eventType) {
                'payment_intent.succeeded' => $this->handlePaymentSucceeded($paymentService),
                'payment_intent.requires_capture' => $this->handlePaymentAuthorized($paymentService),
                'payment_intent.failed' => $this->handlePaymentFailed($paymentService),
                'payment_intent.canceled' => $this->handlePaymentCanceled($paymentService),
                default => $this->handleUnknownEvent($eventType),
            };
        } catch (\Exception $e) {
            Log::error('Airwallex webhook job failed', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    private function handlePaymentSucceeded(PaymentService $paymentService): void
    {
        $paymentService->processSuccessfulPayment($this->webhookData);
    }

    private function handlePaymentAuthorized(PaymentService $paymentService): void
    {
        $paymentService->processAirwallexAuthorization($this->webhookData);
    }

    private function handlePaymentFailed(PaymentService $paymentService): void
    {
        $paymentService->processFailedPayment($this->webhookData);
    }

    private function handlePaymentCanceled(PaymentService $paymentService): void
    {
        $paymentIntentId = $this->webhookData['data']['object']['id'] ?? null;
        if (! $paymentIntentId) {
            return;
        }

        $payment = Payment::where('transaction_id', $paymentIntentId)
            ->where('gateway', PaymentGateway::Airwallex)
            ->first();

        if ($payment) {
            $payment->update([
                'status' => PaymentTransactionStatus::Failed,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $this->webhookData]),
            ]);

            $order = $payment->order;
            $order->update([
                'payment_status' => PaymentStatus::Failed,
            ]);

            app(OrderService::class)->transitionStatus(
                $order,
                OrderStatus::Cancelled,
                'Payment canceled via Airwallex webhook',
            );

            Log::warning('Payment canceled via webhook', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Payment Disputes (chargebacks) — dispute.created, dispute.won,
     * dispute.lost, dispute.rfi_responded, etc. all share this handler
     * since they need identical treatment here: alert admins, touch
     * nothing else. Matched by prefix rather than an enumerated event
     * list so a dispute sub-event Airwallex adds later still reaches
     * admins instead of silently falling into handleUnknownEvent().
     */
    private function handleDispute(string $eventType): void
    {
        $dispute = $this->webhookData['data']['object'] ?? [];
        $paymentIntentId = $dispute['payment_intent_id'] ?? null;

        $payment = $paymentIntentId
            ? Payment::where('transaction_id', $paymentIntentId)
                ->where('gateway', PaymentGateway::Airwallex)
                ->first()
            : null;

        /** @var Order|null $order */
        $order = $payment ? $payment->order : null;

        Log::warning('Airwallex dispute event received', [
            'event_type' => $eventType,
            'dispute_id' => $dispute['id'] ?? null,
            'payment_intent_id' => $paymentIntentId,
            'order_id' => $order?->id,
            'status' => $dispute['status'] ?? null,
            'stage' => $dispute['stage'] ?? null,
        ]);

        NotifyAdminsOfPaymentDispute::dispatch(
            eventType: $eventType,
            orderId: $order?->id,
            orderNumber: $order?->order_number,
            disputeId: $dispute['id'] ?? null,
            status: $dispute['status'] ?? null,
            stage: $dispute['stage'] ?? null,
            amount: isset($dispute['amount']) ? (string) $dispute['amount'] : null,
            currency: $dispute['currency'] ?? null,
            reason: $dispute['reason']['description'] ?? $dispute['reason']['type'] ?? null,
        );
    }

    private function handleUnknownEvent(string $eventType): void
    {
        Log::info('Airwallex webhook unknown event type ignored', [
            'event_type' => $eventType,
            'event_id' => $this->webhookData['id'] ?? null,
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    public function backoff(): array
    {
        return [60, 120, 300]; // 1 min, 2 min, 5 min
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('Airwallex webhook job failed after all retries', [
            'event_id' => $this->webhookData['id'] ?? null,
            'event_type' => $this->webhookData['type'] ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
