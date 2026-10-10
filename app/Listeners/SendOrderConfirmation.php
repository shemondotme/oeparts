<?php

namespace App\Listeners;

use App\Enums\PaymentMethod;
use App\Events\OrderPlaced;
use App\Jobs\SendBankTransferInstructionsEmail;
use App\Jobs\SendOrderConfirmationEmail;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmation
{
    public function handle(OrderPlaced $event): void
    {
        try {
            if ($event->attachInvoice) {
                // Admin-created order: confirmed, invoiced and sent in one go.
                dispatch(new SendOrderConfirmationEmail($event->order, 'en', true))
                    ->onQueue('critical');
            } elseif ($event->order->payment_method === PaymentMethod::BankTransfer) {
                // Placed but not paid: the customer needs the payment
                // instructions now. The confirmation (with the invoice) is
                // sent once the transfer arrives.
                dispatch(new SendBankTransferInstructionsEmail($event->order))
                    ->onQueue('critical');
            }
            // Card / Paysera: the confirmation is sent when the gateway reports
            // the payment, never for an order that may still be abandoned.
        } catch (\Exception $e) {
            Log::error('Failed to dispatch order confirmation email', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
