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
    ) {}

    public function envelope(): Envelope
    {
        $siteName = config('app.name', 'OEMHub');

        return new Envelope(
            subject: "[{$siteName}] New part inquiry: {$this->inquiry->oem_number}",
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
                'locale'  => 'en',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
