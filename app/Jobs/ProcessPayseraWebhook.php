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
 * webhook deliveries are detected and skipped upstream in the controller,
 * so this job itself doesn't need its own idempotency check.
 *
 * Paysera POSTs the order resource itself to the callback URL (no
 * event-type envelope like Airwallex's {id, type, data}) — status values
 * per Paysera's Checkout Modern guide are pending_payment, paid, canceled,
 * closed.
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
        $status = $this->webhookData['status'] ?? null;
        $orderId = $this->webhookData['order_id'] ?? null;

        Log::info('Processing Paysera webhook job', [
            'order_id' => $orderId,
            'status' => $status,
        ]);

        try {
            match ($status) {
                'paid' => $paymentService->processSuccessfulPayseraPayment($this->webhookData),
                'canceled', 'closed' => $paymentService->processFailedPayseraPayment($this->webhookData),
                default => $this->handleUnknownStatus($status),
            };
        } catch (\Exception $e) {
            Log::error('Paysera webhook job failed', [
                'order_id' => $orderId,
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    private function handleUnknownStatus(?string $status): void
    {
        Log::info('Paysera webhook unhandled status ignored', [
            'status' => $status,
            'order_id' => $this->webhookData['order_id'] ?? null,
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
        Log::critical('Paysera webhook job failed after all retries', [
            'order_id' => $this->webhookData['order_id'] ?? null,
            'status' => $this->webhookData['status'] ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
