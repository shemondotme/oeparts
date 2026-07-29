<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerts super_admins that a large (> BulkUpdateProducts::LARGE_BATCH_THRESHOLD
 * row) bulk product update was applied — same "surface risky, wide-blast-radius
 * actions immediately" precedent as BackupFailedMail.
 */
class BulkUpdateAppliedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $adminName,
        public readonly string $actionLabel,
        public readonly int $affectedCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bulk update applied — {$this->affectedCount} products — OeParts",
            tags: ['bulk-update-applied'],
            metadata: ['template_type' => 'bulk_update_applied'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bulk-update-applied',
            text: 'emails.bulk-update-applied-text',
        );
    }
}
