<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Update & Recovery System (Module 21, Chunk 2.6) — alerts super_admins that a
 * backup run failed (or could not start). A failed backup is an availability
 * risk, so it is surfaced immediately (LOCKED DECISION #13).
 */
class BackupFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $profile,
        public readonly string $reason,
        public readonly ?int $runId = null,
        public readonly ?string $failedAt = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Backup failed — OeParts',
            tags: ['backup-failed'],
            metadata: ['template_type' => 'backup_failed'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.backup-failed',
            text: 'emails.backup-failed-text',
        );
    }
}
