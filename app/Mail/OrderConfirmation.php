<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        string $locale = 'en',
        public bool $attachInvoice = false,
        public ?string $invoicePdf = null,
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        $siteName = settings('general.site_name', 'OeParts');
        $subject = trans('emails.order_confirmation.subject', [
            'order_number' => $this->order->order_number,
            'site' => $siteName,
        ], $this->locale);

        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name', $siteName)
            ),
            replyTo: [
                new Address(config('mail.reply_to.address', config('mail.from.address'))),
            ],
            subject: $subject,
            tags: ['order-confirmation'],
            metadata: [
                'order_id' => $this->order->id,
                'template_type' => 'order_confirmation',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            text: 'emails.order-confirmation-text',
            with: [
                'order' => $this->order,
                'locale' => $this->locale,
                'attachInvoice' => $this->attachInvoice,
                'invoiceUrl' => $this->invoiceDownloadUrl(),
            ],
        );
    }

    /**
     * Expiring signed link to the invoice, for customers who would rather
     * download it than open the attachment (or have no login — guest checkout).
     */
    private function invoiceDownloadUrl(): ?string
    {
        if (! $this->attachInvoice || blank($this->order->invoice_number)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'frontend.order.invoice.signed',
            now()->addDays(30),
            ['lang' => $this->locale, 'order' => $this->order->id],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->attachInvoice) {
            return [];
        }

        if ($this->invoicePdf !== null) {
            return [
                Attachment::fromData(fn () => $this->invoicePdf, "invoice-{$this->order->order_number}.pdf")
                    ->withMime('application/pdf'),
            ];
        }

        return [OrderInvoiceMail::pdfAttachment($this->order)];
    }
}
