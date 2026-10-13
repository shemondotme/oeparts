<?php

namespace App\Listeners;

use App\Services\Instance\SafeMode;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * The last line of defence for mail on a copy of the shop. Every message passes through here just before it
 * is handed to a transport, whichever mailer was chosen and whoever asked for it (a mailable, a notification,
 * a queued job), so a copy cannot mail a real customer by any route.
 *
 *   production  nothing changes.
 *   quarantine  every message is cancelled.
 *   staging     recipients that are not on the allow-list are removed; a message with nobody left is
 *               cancelled; what is delivered gets a [STAGING] subject.
 *
 * Returning false from a MessageSending listener cancels the message (Laravel's own contract). A cancelled
 * message is logged by recipient count and subject only, never the body, which may hold a customer's details.
 *
 * Registered in EventServiceProvider::$listen, because the application turns automatic discovery off.
 */
class EnforceInstanceMailPolicy
{
    public function __construct(private readonly SafeMode $safeMode) {}

    public function handle(MessageSending $event): ?bool
    {
        if ($this->safeMode->isProduction()) {
            return null;
        }

        $message = $event->message;
        $recipients = $this->recipients($message);
        $allowed = [];

        foreach (['to', 'cc', 'bcc'] as $field) {
            $allowed[$field] = array_values(array_filter(
                $recipients[$field],
                fn (Address $address): bool => $this->safeMode->mailVerdict($address->getAddress()) === SafeMode::MAIL_SEND,
            ));
        }

        $remaining = count($allowed['to']) + count($allowed['cc']) + count($allowed['bcc']);
        $total = count($recipients['to']) + count($recipients['cc']) + count($recipients['bcc']);

        if ($remaining === 0) {
            Log::info("Mail cancelled on this {$this->safeMode->mode()->value} copy: \"{$message->getSubject()}\" to {$total} recipient(s).");

            return false;
        }

        if ($remaining < $total) {

            foreach (['to', 'cc', 'bcc'] as $field) {
                $message->getHeaders()->remove(ucfirst($field));

                if ($allowed[$field] !== []) {
                    $message->{$field}(...$allowed[$field]);
                }
            }

            Log::info('Mail on this staging copy was narrowed to the allow-list: '.($total - $remaining).' recipient(s) removed.');
        }

        $prefix = $this->safeMode->mailSubjectPrefix();
        $subject = (string) $message->getSubject();

        if ($prefix !== '' && ! str_starts_with($subject, $prefix)) {
            $message->subject($prefix.$subject);
        }

        return null;
    }

    /**
     * @return array{to: array<int, Address>, cc: array<int, Address>, bcc: array<int, Address>}
     */
    private function recipients(Email $message): array
    {
        return ['to' => $message->getTo(), 'cc' => $message->getCc(), 'bcc' => $message->getBcc()];
    }
}
