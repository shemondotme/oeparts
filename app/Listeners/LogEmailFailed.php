<?php

namespace App\Listeners;

use App\Enums\EmailTemplate;
use App\Enums\LogStatus;
use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageFailed;
use Illuminate\Support\Facades\Log;

/**
 * Log failed email sends to the email_logs table.
 *
 * Triggered by Illuminate\Mail\Events\MessageFailed, which fires
 * when the mailer transport throws an exception during send.
 */
class LogEmailFailed
{
    public function handle(MessageFailed $event): void
    {
        try {
            $message = $event->message;
            $error = $event->error;

            $to = method_exists($message, 'getTo') ? $message->getTo() : [];
            $toEmail = $to ? collect($to)->first()?->getAddress() : null;

            if (! $toEmail) {
                return;
            }

            $subject = method_exists($message, 'getSubject') ? ($message->getSubject() ?? '') : '';

            EmailLog::create([
                'to_email' => $toEmail,
                'subject' => $subject,
                'template_type' => EmailTemplate::OrderConfirmation, // best-effort fallback
                'related_id' => null,
                'related_type' => null,
                'status' => LogStatus::Failed,
                'error_message' => $error->getMessage(),
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('LogEmailFailed listener itself failed', ['error' => $e->getMessage()]);
        }
    }
}
