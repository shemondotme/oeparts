<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Update & Recovery System — result notice for an update apply, either an
 * unattended security auto-apply (config('updates.auto_apply_security')) or
 * a manually-triggered admin apply. Always fires, success or failure — a
 * quiet update outcome (whether the admin's tab is still open or not) is
 * exactly the kind of silent surprise/silent failure this is meant to
 * prevent. Formerly AutoUpdateResultMail, generalized for both triggers.
 */
class UpdateResultMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string,mixed>  $result  from_version, to_version, success,
     *                                       rolled_back, error, started_at,
     *                                       trigger ('auto'|'manual'|'restore')
     */
    public function __construct(
        public readonly array $result,
    ) {}

    public function envelope(): Envelope
    {
        $success = (bool) ($this->result['success'] ?? false);
        $to = $this->result['to_version'] ?? '';
        $trigger = $this->result['trigger'] ?? 'auto';

        // 'restore' (a production backup restore, dispatched from
        // ProductionRestoreService/RunProductionRestoreJob) used to fall
        // through the 'not auto' branch and get labeled a plain "Update" —
        // during an already-stressful disaster-recovery event, telling an
        // admin their emergency restore was "an update applied via System →
        // System Updates" is actively misleading, not just imprecise.
        $prefix = match ($trigger) {
            'auto' => 'Auto-update',
            'restore' => 'Restore',
            default => 'Update',
        };

        return new Envelope(
            subject: ($success ? $prefix.' applied' : $prefix.' FAILED').' — OeParts '.$to,
            tags: ['system-update', $trigger === 'auto' ? 'auto-apply' : ($trigger === 'restore' ? 'restore' : 'manual-apply')],
            metadata: ['template_type' => 'update_result', 'trigger' => $trigger],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.update-result',
            text: 'emails.update-result-text',
        );
    }
}
