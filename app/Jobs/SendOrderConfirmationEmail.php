<?php

namespace App\Jobs;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Services\InvoiceService;
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
        public readonly ?string $locale = null,
        public readonly bool $attachInvoice = false,
    ) {
        $this->onQueue('critical');
        // Dispatched from inside the order / payment transaction: wait for the
        // commit, or the worker can render the mail from the order as it was
        // BEFORE it (no invoice number, old status) or not find a new order at all.
        $this->afterCommit();
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

        // Render the invoice up front. If it cannot be built the customer still
        // gets the confirmation (without the "invoice attached" claim) instead
        // of the whole mail failing and retrying for a PDF that may never render.
        $invoicePdf = null;
        if ($this->attachInvoice) {
            try {
                $invoicePdf = app(InvoiceService::class)->generate($this->order, false, true)->output();
            } catch (\Throwable $e) {
                Log::error('Order confirmation sent without its invoice PDF', [
                    'order_id' => $this->order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Mail::to($toEmail)->send(new OrderConfirmation($this->order, $this->locale ?? $this->order->mailLocale(), $invoicePdf !== null, $invoicePdf));
    }
}
