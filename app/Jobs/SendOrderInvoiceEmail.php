<?php

namespace App\Jobs;

use App\Mail\OrderInvoiceMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the invoice (PDF attached) on its own. Used when an order was
 * confirmed BEFORE it was paid for — a card payment that was only authorized
 * (held) at order time and captured later, when it ships — so the confirmation
 * could not carry an invoice yet.
 */
class SendOrderInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly Order $order,
        public readonly ?string $locale = null,
    ) {
        $this->onQueue('critical');
        // Dispatched from inside the payment transaction — see SendOrderConfirmationEmail.
        if (config('queue.default') !== 'sync') {
            $this->afterCommit();
        }
    }

    public function handle(): void
    {
        $toEmail = $this->order->recipientEmail();

        if (empty($toEmail)) {
            Log::warning('Skipped order invoice email: no recipient address', ['order_id' => $this->order->id]);

            return;
        }

        if (blank($this->order->invoice_number)) {
            Log::warning('Skipped order invoice email: the order has no invoice number yet', ['order_id' => $this->order->id]);

            return;
        }

        Mail::to($toEmail)->send(new OrderInvoiceMail($this->order, $this->locale ?? $this->order->mailLocale()));
    }
}
