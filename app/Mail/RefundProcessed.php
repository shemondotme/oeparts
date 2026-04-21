<?php

namespace App\Mail;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundProcessed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RefundRequest $refund,
        string $locale = 'en',
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('emails.refund_processed.subject', [
                'order_number' => $this->refund->order->order_number,
            ], $this->locale),
            tags: ['refund-processed'],
            metadata: [
                'refund_id'     => $this->refund->id,
                'template_type' => 'refund_processed',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.refund-processed',
            text: 'emails.refund-processed-text',
            with: [
                'refund' => $this->refund,
                'locale' => $this->locale,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
