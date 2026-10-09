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
 * A polite "this invoice is still open" email: what is outstanding, how to pay it,
 * and the invoice PDF again. English-only, like the invoices themselves.
 */
class CustomInvoiceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CustomInvoice $invoice,
        private readonly string $pdfContent,
    ) {
        $this->locale = app(CustomInvoiceService::class)->languageOf($invoice);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('invoice_doc.reminder_subject', ['number' => $this->invoice->invoice_number, 'company' => settings('company.name', 'OeParts')], $this->locale),
            tags: ['custom-invoice-reminder'],
            metadata: [
                'custom_invoice_id' => $this->invoice->id,
                'template_type' => 'custom_invoice_reminder',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.custom-invoice-reminder',
            text: 'emails.custom-invoice-reminder-text',
            with: [
                'invoice' => $this->invoice,
                'documentType' => $this->invoice->document_type,
                'balance' => $this->invoice->balanceDue(),
                'daysOverdue' => $this->invoice->daysOverdue(),
                'bank' => $this->invoice->payment_method === InvoicePaymentMethod::BankTransfer
                    ? app(InvoiceService::class)->bankDetailsFor($this->invoice->bank_account_id, $this->invoice->currency)
                    : null,
                'locale' => $this->locale,
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
