<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NewsletterSubscriber $subscriber,
        public readonly string $confirmUrl,
        string $locale = 'en',
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans('emails.newsletter_confirm.subject', [], $this->locale),
            tags: ['newsletter'],
            metadata: ['template_type' => 'newsletter_confirm'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter-confirmation',
            text: 'emails.newsletter-confirmation-text',
            with: [
                'subscriber'  => $this->subscriber,
                'confirmUrl'  => $this->confirmUrl,
                'locale'      => $this->locale,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
