<?php

namespace App\Jobs;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

        Mail::to($toEmail)->send(new OrderConfirmation($this->order, $this->locale));
    }
}
