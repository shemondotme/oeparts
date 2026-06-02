<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCartReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $cartSnapshot,
        string $locale = 'en',
        public readonly ?string $customerName = null,
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('emails.abandoned_cart.subject', [], $this->locale),
            tags: ['abandoned-cart'],
            metadata: ['template_type' => 'abandoned_cart'],
        );
    }

    public function content(): Content
    {
        $items = $this->cartSnapshot['items'] ?? [];
        $total = $this->cartSnapshot['total'] ?? '0.00';
        $name = $this->customerName ?? $this->cartSnapshot['customer_name'] ?? 'Customer';

        return new Content(
            view: 'emails.abandoned-cart',
            text: 'emails.abandoned-cart-text',
            with: [
                'cartSnapshot' => $this->cartSnapshot,
                'locale' => $this->locale,
                'items' => $items,
                'total' => $total,
                'customerName' => $name,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
