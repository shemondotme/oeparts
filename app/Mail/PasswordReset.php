<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $resetUrl,
        string $locale = 'en',
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        $siteName = settings('general.site_name', 'OeParts');

        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name', $siteName)
            ),
            subject: trans('emails.password_reset.subject', [], $this->locale),
            tags: ['password-reset'],
            metadata: ['template_type' => 'password_reset'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            text: 'emails.password-reset-text',
            with: [
                'resetUrl' => $this->resetUrl,
                'locale' => $this->locale,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
