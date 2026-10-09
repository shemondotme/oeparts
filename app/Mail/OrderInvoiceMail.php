<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Emails an order's invoice PDF to the customer. Sent on demand from the
 * admin order page ("Email Invoice to Customer"); the PDF is rendered from the
 * order at send time so it always matches what the admin sees.
 */
class OrderInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        string $locale = 'en',
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        $siteName = settings('general.site_name', 'OeParts');

        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name', $siteName)),
            replyTo: [new Address(config('mail.reply_to.address', config('mail.from.address')))],
            subject: trans('emails.order_invoice.subject', [
                'invoice' => $this->order->invoice_number ?: $this->order->order_number,
                'order_number' => $this->order->order_number,
                'site' => $siteName,
            ], $this->locale),
            tags: ['order-invoice'],
            metadata: [
                'order_id' => $this->order->id,
                'template_type' => 'order_invoice',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-invoice',
            text: 'emails.order-invoice-text',
            with: [
                'order' => $this->order,
                'locale' => $this->locale,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [self::pdfAttachment($this->order)];
    }

    /**
     * The invoice PDF as a mail attachment, shared with the order confirmation
     * (which can carry the same file). Rendered lazily, when the message is built.
     */
    public static function pdfAttachment(Order $order): Attachment
    {
        return Attachment::fromData(
            fn () => app(InvoiceService::class)->generate($order, false, true)->output(),
            "invoice-{$order->order_number}.pdf",
        )->withMime('application/pdf');
    }
}
