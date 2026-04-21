<?php

namespace App\Jobs;

use App\Mail\OrderShipped;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTrackingUpdateEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $locale = app()->getLocale();

        $toEmail = $this->order->user?->email ?? $this->order->guest_email;

        Mail::to($toEmail)->send(new OrderShipped($this->order, $locale));
    }
}
