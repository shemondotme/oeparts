<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Jobs\SendOrderConfirmationEmail;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmation
{
    public function handle(OrderPlaced $event): void
    {
        try {
            dispatch(new SendOrderConfirmationEmail($event->order))
                ->onQueue('critical');
        } catch (\Exception $e) {
            Log::error('Failed to dispatch order confirmation email', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
