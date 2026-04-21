<?php

namespace App\Mail;

use App\Enums\RefundStatus;
use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundStatusUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly RefundRequest $refund,
        public readonly RefundStatus $oldStatus,
        public readonly RefundStatus $newStatus,
        string $locale = 'en',
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('emails.refund_status.subject', [
                'order_number' => $this->refund->order->order_number,
            ], $this->locale),
            tags: ['refund-status'],
            metadata: [
                'refund_id'     => $this->refund->id,
                'template_type' => 'refund_status',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.refund-status-update',
            text: 'emails.refund-status-update-text',
            with: [
                'refund'    => $this->refund,
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
