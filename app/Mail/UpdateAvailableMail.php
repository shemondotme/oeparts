<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Update & Recovery System (Module 21, Chunk 1.4) — "a new release is available"
 * notice to super_admins. Security releases are highlighted.
 */
class UpdateAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<string,mixed> $status UpdateStatus::toArray() */
    public function __construct(
        public readonly array $status,
    ) {}

    public function envelope(): Envelope
    {
        $security = (bool) ($this->status['security'] ?? false);
        $latest = $this->status['latest_version'] ?? '';

        return new Envelope(
            subject: ($security ? 'Security update available' : 'Update available').' — OeParts '.$latest,
            tags: ['system-update'],
            metadata: ['template_type' => 'update_available'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.update-available',
            text: 'emails.update-available-text',
            with: [
                'subject' => (($this->status['security'] ?? false) ? 'Security update available' : 'Update available'),
            ],
        );
    }
}
