<?php

namespace App\Jobs;

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
            match ($eventType) {
                'payment_intent.succeeded' => $this->handlePaymentSucceeded($paymentService),
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

    private function handlePaymentFailed(PaymentService $paymentService): void
    {
        $paymentService->processFailedPayment($this->webhookData);
    }

    private function handlePaymentCanceled(PaymentService $paymentService): void
    {
        $paymentIntentId = $this->webhookData['data']['object']['id'] ?? null;
        if (!$paymentIntentId) {
            return;
        }

        $payment = \App\Models\Payment::where('transaction_id', $paymentIntentId)
            ->where('gateway', \App\Enums\PaymentGateway::Airwallex)
            ->first();

        if ($payment) {
            $payment->update([
                'status' => \App\Enums\PaymentTransactionStatus::Failed,
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['webhook' => $this->webhookData]),
            ]);

            $order = $payment->order;
            $order->update([
                'payment_status' => \App\Enums\PaymentStatus::Failed,
            ]);

            app(\App\Services\OrderService::class)->transitionStatus(
                $order,
                \App\Enums\OrderStatus::Cancelled,
                'Payment canceled via Airwallex webhook',
            );

            Log::warning('Payment canceled via webhook', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ]);
        }
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