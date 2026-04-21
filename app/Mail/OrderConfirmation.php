<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Order $order,
        string $locale = 'en'
    ) {
        $this->locale = $locale;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $siteName = config('app.name', 'OEMHub');
        $subject = trans('emails.order_confirmation.subject', [
            'order_number' => $this->order->order_number,
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

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            text: 'emails.order-confirmation-text',
            with: [
                'order' => $this->order,
                'locale' => $this->locale,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}