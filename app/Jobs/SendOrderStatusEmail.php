<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Mail\OrderStatusUpdate;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOrderStatusEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $oldStatus,
        public readonly OrderStatus $newStatus,
        public readonly string $locale = 'en',
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $toEmail = $this->order->user?->email ?? $this->order->guest_email;

        Mail::to($toEmail)
            ->send(new OrderStatusUpdate($this->order, $this->oldStatus, $this->newStatus, $this->locale));
    }
}
