<?php

namespace App\Jobs;

use App\Mail\RefundProcessed;
use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendRefundProcessedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly RefundRequest $refund,
        public readonly string $locale = 'en',
    ) {
        $this->onQueue('critical');
    }

    public function handle(): void
    {
        $order = $this->refund->order;
        $toEmail = $order->user?->email ?? $order->guest_email;

        Mail::to($toEmail)->send(new RefundProcessed($this->refund, $this->locale));
    }
}
