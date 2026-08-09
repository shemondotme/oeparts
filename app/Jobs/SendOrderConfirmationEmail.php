<?php

namespace App\Jobs;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly Order $order,
        public readonly string $locale = 'en',
    ) {
        $this->onQueue('critical');
    }

    public function handle(): void
    {
        $toEmail = $this->order->user?->email ?? $this->order->guest_email;

        // Neither a linked user's email nor a guest_email — nothing to send
        // to. Mail::to(null) throws, which would otherwise burn all 3
        // retries/backoff cycles on an order this job can never deliver for.
        if (empty($toEmail)) {
            Log::warning('Skipped order confirmation email: no recipient address', ['order_id' => $this->order->id]);
            return;
        }

        Mail::to($toEmail)->send(new OrderConfirmation($this->order, $this->locale));
    }
}
