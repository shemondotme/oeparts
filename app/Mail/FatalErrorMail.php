<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Something broke in the admin panel" notice to super_admins — the
 * fatal-error email sent by App\Exceptions\AdminFatalErrorNotifier.
 */
class FatalErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string,mixed> $error exception_class, message, file, line, url, occurred_at */
    public function __construct(
        public readonly array $error,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Admin panel error — OeParts',
            tags: ['fatal-error'],
            metadata: ['template_type' => 'admin_fatal_error'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.fatal-error',
            text: 'emails.fatal-error-text',
        );
    }
}
