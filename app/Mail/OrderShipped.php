<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShipped extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        string $locale = 'en',
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('emails.order_shipped.subject', [
                'order_number' => $this->order->order_number,
            ], $this->locale),
            tags: ['order-shipped'],
            metadata: [
                'order_id'      => $this->order->id,
                'template_type' => 'order_shipped',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-shipped',
            text: 'emails.order-shipped-text',
            with: [
                'order'  => $this->order,
                'locale' => $this->locale,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
