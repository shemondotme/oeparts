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

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order
    ) {
        $this->onQueue('critical');
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoiceService): void
    {
        // Generate invoice PDF
        $pdf = $invoiceService->generate($this->order, false, true);

        // Store PDF in storage — invoices contain customer PII, so this must
        // never sit on the public disk (rule: private data stays off storage/app/public).
        $filename = 'invoices/' . now()->format('Y/m') . "/{$this->order->invoice_number}.pdf";
        Storage::disk('local')->put($filename, $pdf->output());

        // Optionally attach to order or send email
        // $this->order->update(['invoice_path' => $filename]);
    }
}
