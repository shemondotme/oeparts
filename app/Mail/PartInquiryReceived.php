<?php

namespace App\Mail;

use App\Models\PartInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartInquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PartInquiry $inquiry,
        string $locale = 'en',
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('emails.part_inquiry.subject', ['oem' => $this->inquiry->oem_number], $this->locale),
            tags: ['part-inquiry'],
            metadata: ['inquiry_id' => $this->inquiry->id],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.part-inquiry-received',
            text: 'emails.part-inquiry-received-text',
            with: [
                'inquiry' => $this->inquiry,
                'locale' => $this->locale,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
