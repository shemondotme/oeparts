<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order
    ) {}

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoiceService): void
    {
        // Generate invoice PDF
        $pdf = $invoiceService->generate($this->order, false);

        // Store PDF in storage
        $filename = "invoices/{$this->order->invoice_number}.pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        // Optionally attach to order or send email
        // $this->order->update(['invoice_path' => $filename]);
    }
}
