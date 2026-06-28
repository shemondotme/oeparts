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
 * ProcessAirwallexWebhook — processes Airwallex webhook events asynchronously.
 *
 * This job runs on the 'critical' queue to ensure timely processing of payment events.
 * It handles:
 *  - payment_intent.succeeded → mark order as paid
 *  - payment_intent.failed → mark order as failed
 *  - payment_intent.canceled → mark order as canceled
 *  - Other events are logged but ignored.
 *
 * The job is idempotent: duplicate events are detected and skipped in the controller.
 */
class ProcessAirwallexWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
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

    /**
     * Execute the job.
     */
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

    /**
     * Handle successful payment.
     */
    private function handlePaymentSucceeded(PaymentService $paymentService): void
    {
        $paymentService->processSuccessfulPayment($this->webhookData);
    }

    /**
     * Handle failed payment.
     */
    private function handlePaymentFailed(PaymentService $paymentService): void
    {
        $paymentService->processFailedPayment($this->webhookData);
    }

    /**
     * Handle canceled payment.
     */
    private function handlePaymentCanceled(PaymentService $paymentService): void
    {
        // Similar to failed, but we might want different logging
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

    /**
     * Handle unknown event type (log and ignore).
     */
    private function handleUnknownEvent(string $eventType): void
    {
        Log::info('Airwallex webhook unknown event type ignored', [
            'event_type' => $eventType,
            'event_id' => $this->webhookData['id'] ?? null,
        ]);
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 120, 300]; // 1 min, 2 min, 5 min
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Airwallex webhook job failed after all retries', [
            'event_id' => $this->webhookData['id'] ?? null,
            'event_type' => $this->webhookData['type'] ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optionally notify admin via email or other channel
        // dispatch(new NotifyAdminJob('Airwallex webhook processing failed', $exception));
    }
}