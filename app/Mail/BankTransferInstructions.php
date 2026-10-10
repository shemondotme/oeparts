<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent the moment a bank-transfer order is placed: the order is received but
 * NOT yet paid, so this carries what the customer needs to pay it (IBAN, the
 * payment reference, the amount and the deadline). The "order confirmed"
 * email, with the invoice, follows once the transfer has arrived.
 */
class BankTransferInstructions extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{bank_name: string, iban: string, bic: string, account_holder: string, reference: string, amount: mixed, currency: string, expiry_hours: mixed}  $bank
     */
    public function __construct(
        public Order $order,
        public array $bank,
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
            subject: trans('emails.bank_transfer.subject', [
                'order_number' => $this->order->order_number,
                'site' => $siteName,
            ], $this->locale),
            tags: ['bank-transfer-instructions'],
            metadata: [
                'order_id' => $this->order->id,
                'template_type' => 'bank_transfer_instructions',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bank-transfer-instructions',
            text: 'emails.bank-transfer-instructions-text',
            with: [
                'order' => $this->order,
                'bank' => $this->bank,
                'locale' => $this->locale,
                'deadline' => $this->order->created_at->copy()->addHours((int) ($this->bank['expiry_hours'] ?? 48)),
            ],
        );
    }
}
