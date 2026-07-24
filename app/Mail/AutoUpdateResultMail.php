<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Update & Recovery System — result notice for an UNATTENDED security-update
 * auto-apply (config('updates.auto_apply_security')). Automatic background
 * updates in general tend to be criticized for being too quiet about
 * outcomes — this always fires, success or failure, so a security patch
 * applying itself is never a silent surprise (or a silent failure).
 */
class AutoUpdateResultMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string,mixed>  $result  from_version, to_version, success,
     *                                        rolled_back, error, started_at
     */
    public function __construct(
        public readonly array $result,
    ) {}

    public function envelope(): Envelope
    {
        $success = (bool) ($this->result['success'] ?? false);
        $to = $this->result['to_version'] ?? '';

        return new Envelope(
            subject: ($success ? 'Auto-update applied' : 'Auto-update FAILED').' — OeParts '.$to,
            tags: ['system-update', 'auto-apply'],
            metadata: ['template_type' => 'auto_update_result'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auto-update-result',
            text: 'emails.auto-update-result-text',
        );
    }
}
