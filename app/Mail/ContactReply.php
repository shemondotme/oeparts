<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContactMessage $contactMessage,
        public readonly string $replyBody,
        string $locale = 'en',
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('emails.contact_reply.subject', [], $this->locale),
            tags: ['contact-reply'],
            metadata: [
                'contact_message_id' => $this->contactMessage->id,
                'template_type'      => 'contact_reply',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-reply',
            text: 'emails.contact-reply-text',
            with: [
                'contactMessage' => $this->contactMessage,
                'replyBody'      => $this->replyBody,
                'locale'         => $this->locale,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
