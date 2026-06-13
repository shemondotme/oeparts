<?php

namespace App\Listeners;

use App\Events\CartAbandoned;
use App\Jobs\SendAbandonedCartEmail;
use Illuminate\Support\Facades\Log;

class RecoverAbandonedCart
{
    public function handle(CartAbandoned $event): void
    {
        $cart = $event->cart;

        if (!$cart->user && !$cart->guest_email) {
            return;
        }

        $email = $cart->guest_email ?? $cart->user?->email;
        if (!$email) {
            return;
        }

        try {
            dispatch(new SendAbandonedCartEmail($cart))
                ->onQueue('default');
        } catch (\Exception $e) {
            Log::error('Failed to dispatch abandoned cart email', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
