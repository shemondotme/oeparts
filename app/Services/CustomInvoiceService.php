<?php

namespace App\Services;

use App\Enums\CustomInvoiceStatus;
use App\Mail\CustomInvoiceMail;
use App\Models\CustomInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

/**
 * Stand-alone invoices: numbering, PDF rendering and emailing the client.
 * Order invoices stay in InvoiceService; both share the same invoice Sequence
 * and the same bank-details block.
 */
class CustomInvoiceService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Next number from the shared invoice sequence (same run as order invoices).
     */
    public function nextNumber(): string
    {
        return $this->sequences->nextInvoiceNumber();
    }

    public function pdf(CustomInvoice $invoice): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.custom-invoice', $this->viewData($invoice));
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(CustomInvoice $invoice): array
    {
        return [
            'invoice' => $invoice,
            'items' => $invoice->normalizedItems(),
            'bank' => $this->invoices->bankDetails(),
            'settings' => [
                'company_name' => settings('company.name', 'OeParts'),
                'company_address' => settings('company.address', ''),
                'company_vat' => settings('company.vat_number', ''),
                'company_registration' => settings('company.registration_number', ''),
                'company_email' => settings('company.email', 'info@oeparts.lt'),
                'company_phone' => settings('company.phone', ''),
            ],
        ];
    }

    public function filename(CustomInvoice $invoice): string
    {
        return 'invoice-'.$invoice->invoice_number.'.pdf';
    }

    /**
     * Email the PDF to the client. A draft becomes "sent"; re-sending a sent
     * or paid invoice just resends it (and refreshes sent_at).
     *
     * @throws \RuntimeException when the invoice has no client email or is cancelled
     */
    public function send(CustomInvoice $invoice): void
    {
        if ($invoice->status === CustomInvoiceStatus::Cancelled) {
            throw new \RuntimeException('A cancelled invoice cannot be sent.');
        }

        if (blank($invoice->client_email)) {
            throw new \RuntimeException('This invoice has no client email address.');
        }

        Mail::to($invoice->client_email)->send(new CustomInvoiceMail($invoice, $this->pdf($invoice)->output()));

        $invoice->forceFill([
            'sent_at' => now(),
            'status' => $invoice->status === CustomInvoiceStatus::Draft ? CustomInvoiceStatus::Sent : $invoice->status,
        ])->save();
    }

    public function markPaid(CustomInvoice $invoice): void
    {
        $invoice->forceFill([
            'status' => CustomInvoiceStatus::Paid,
            'paid_at' => now(),
        ])->save();
    }

    public function cancel(CustomInvoice $invoice): void
    {
        $invoice->forceFill(['status' => CustomInvoiceStatus::Cancelled])->save();
    }
}
