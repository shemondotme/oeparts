<?php

namespace App\Jobs;

use App\Enums\RefundStatus;
use App\Mail\RefundStatusUpdate;
use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRefundStatusEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly RefundRequest $refund,
        public readonly RefundStatus $oldStatus,
        public readonly RefundStatus $newStatus,
        public readonly string $locale = 'en',
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $order = $this->refund->order;
        $toEmail = $order->user?->email ?? $order->guest_email;

        // Matches SendOrderConfirmationEmail's own established pattern:
        // Mail::to(null) throws, which would otherwise burn all 3 retries/
        // backoff cycles on a refund this job can never deliver for.
        if (empty($toEmail)) {
            Log::warning('Skipped refund status email: no recipient address', ['refund_request_id' => $this->refund->id]);

            return;
        }

        Mail::to($toEmail)
            ->send(new RefundStatusUpdate($this->refund, $this->oldStatus, $this->newStatus, $this->locale));
    }
}
