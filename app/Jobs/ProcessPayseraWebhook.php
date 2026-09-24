<?php

namespace App\Jobs;

use App\Services\PaymentService;
use App\Support\Payments\PayseraWebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs on the 'critical' queue for timely payment processing. Duplicate
 * callbacks are detected and skipped upstream in the controller, and every
 * handler is state-guarded, so this job needs no idempotency check of its own.
 *
 * Acts on the shapes in Paysera's "Webhooks" guide (see PayseraWebhookEvent):
 *
 *  - order  + status `paid`      -> fulfil (order total equals amount paid)
 *  - order  + status `canceled`  -> payment failed (canceled before anything was paid)
 *  - payment + status `chargeback` -> alert admins (dispute initiated)
 *
 * Everything else is logged and ignored on purpose: `amount_paid_updated`
 * while still `pending_payment` is a partial payment, and payment-level
 * failed/rejected/expired statuses describe one ATTEMPT — the customer can
 * retry through the same payment link, so they must not fail the order.
 */
class ProcessPayseraWebhook implements ShouldQueue
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
        $event = PayseraWebhookEvent::fromArray($this->webhookData);

        Log::info('Processing Paysera webhook job', [
            'paysera_order_id' => $event->payseraOrderId,
            'event_type' => $event->type,
            'event_name' => $event->name,
            'order_status' => $event->orderStatus,
            'payment_status' => $event->paymentStatus,
        ]);

        try {
            match (true) {
                $event->isOrderEvent() && $event->orderStatus === 'paid' => $paymentService->processSuccessfulPayseraPayment($this->webhookData),
                $event->isOrderEvent() && $event->orderStatus === 'canceled' => $paymentService->processFailedPayseraPayment($this->webhookData),
                $event->isPaymentEvent() && $event->paymentStatus === 'chargeback' => $paymentService->processPayseraChargeback($this->webhookData),
                default => $this->handleIgnoredEvent($event),
            };
        } catch (\Exception $e) {
            Log::error('Paysera webhook job failed', [
                'paysera_order_id' => $event->payseraOrderId,
                'event_name' => $event->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    private function handleIgnoredEvent(PayseraWebhookEvent $event): void
    {
        Log::info('Paysera webhook event ignored (no action defined)', [
            'paysera_order_id' => $event->payseraOrderId,
            'event_type' => $event->type,
            'event_name' => $event->name,
            'order_status' => $event->orderStatus,
            'payment_status' => $event->paymentStatus,
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
        $event = PayseraWebhookEvent::fromArray($this->webhookData);

        Log::critical('Paysera webhook job failed after all retries', [
            'paysera_order_id' => $event->payseraOrderId,
            'event_name' => $event->name,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
