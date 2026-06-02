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
use Illuminate\Support\Facades\Mail;

class SendRefundStatusEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        Mail::to($toEmail)
            ->send(new RefundStatusUpdate($this->refund, $this->oldStatus, $this->newStatus, $this->locale));
    }
}
