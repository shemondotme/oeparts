<?php

namespace App\Mail;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $oldStatus,
        public readonly OrderStatus $newStatus,
        string $locale = 'en',
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('emails.order_status.subject', [
                'order_number' => $this->order->order_number,
                'status'       => $this->newStatus->value,
            ], $this->locale),
            tags: ['order-status'],
            metadata: [
                'order_id'      => $this->order->id,
                'template_type' => 'order_status',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-status-update',
            text: 'emails.order-status-update-text',
            with: [
                'order'     => $this->order,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
                'locale'    => $this->locale,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
