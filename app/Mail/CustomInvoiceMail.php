<?php

namespace App\Mail;

use App\Enums\InvoicePaymentMethod;
use App\Models\CustomInvoice;
use App\Services\CustomInvoiceService;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a stand-alone invoice to the client with the PDF attached. The PDF
 * bytes are rendered by the caller and passed in so the attachment is exactly
 * what the admin previewed. Invoices are English-only documents.
 */
class CustomInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CustomInvoice $invoice,
        private readonly string $pdfContent,
    ) {
        $this->locale = 'en';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->invoice->document_type->getLabel().' '.$this->invoice->invoice_number.' from '.settings('company.name', 'OeParts'),
            tags: ['custom-invoice'],
            metadata: [
                'custom_invoice_id' => $this->invoice->id,
                'template_type' => 'custom_invoice',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.custom-invoice',
            text: 'emails.custom-invoice-text',
            with: [
                'invoice' => $this->invoice,
                'documentType' => $this->invoice->document_type,
                'bank' => $this->invoice->document_type->requestsPayment() && $this->invoice->payment_method === InvoicePaymentMethod::BankTransfer
                    ? app(InvoiceService::class)->bankDetailsFor($this->invoice->bank_account_id, $this->invoice->currency)
                    : null,
                'locale' => 'en',
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, app(CustomInvoiceService::class)->filename($this->invoice))
                ->withMime('application/pdf'),
        ];
    }
}
