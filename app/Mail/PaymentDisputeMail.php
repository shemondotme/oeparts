<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerts super_admins that a gateway reported a payment dispute/chargeback.
 * Disputes carry a bank-imposed response deadline (commonly 7-21 days) that,
 * if missed, auto-loses the case — email is the durable channel for that,
 * on top of the in-panel bell. This is alert-only: no order status, refund,
 * or payment record is touched automatically (see
 * [[project_bulletproof_testing_2026_09]] Phase 10 — the user explicitly
 * chose "alert admin only" over auto-hold/auto-cancel).
 */
class PaymentDisputeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $eventType,
        public readonly ?string $orderNumber = null,
        public readonly ?string $disputeId = null,
        public readonly ?string $status = null,
        public readonly ?string $stage = null,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment dispute — OeParts',
            tags: ['payment-dispute'],
            metadata: ['template_type' => 'payment_dispute'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-dispute',
            text: 'emails.payment-dispute-text',
        );
    }
}
