<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public Order $order
    ) {
        $this->onQueue('critical');
    }

    public function handle(InvoiceService $invoiceService): void
    {
        // Delegate to InvoiceService::saveToStorage() rather than duplicating
        // PDF-generation/path logic here. The previous inline version keyed
        // the filename on invoice_number and a now()-derived date directory —
        // invoice_number is null until an order reaches Paid status (see
        // OrderService::transitionStatus()), but this job is dispatched
        // immediately at order creation (CheckoutService::createOrder()), so
        // every pending order in the same month collided on the same
        // "invoices/{Y}/{m}/.pdf" path, each overwriting the last. It also
        // wrote to a path exists()/getFromStorage() never looked at, so those
        // two lookups never found what this job actually saved.
        // saveToStorage() uses order_number, which is unique and always set.
        $invoiceService->saveToStorage($this->order);
    }
}
