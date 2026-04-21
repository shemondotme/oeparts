<?php

namespace App\Listeners;

use App\Enums\EmailTemplate;
use App\Enums\LogStatus;
use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;

class LogEmailSent
{
    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        // __laravel_mailable holds the fully-qualified class name of the Mailable
        $mailableClass = $event->data['__laravel_mailable'] ?? null;

        // Extract recipient email (getTo() returns Symfony\Component\Mime\Address[])
        $to = $message->getTo();
        $toEmail = $to ? collect($to)->first()?->getAddress() : null;

        if (!$toEmail) {
            return;
        }

        // Determine template type from mailable class name
        $templateType = $this->determineTemplateType($mailableClass);

        // Extract subject
        $subject = $message->getSubject() ?? '';

        // Extract related model info from view data (public properties of the Mailable)
        $relatedId = null;
        $relatedType = null;

        $order = $event->data['order'] ?? null;
        if ($order instanceof \App\Models\Order) {
            $relatedId   = $order->id;
            $relatedType = 'order';
        }

        // Create log entry
        EmailLog::create([
            'to_email' => $toEmail,
            'subject' => $subject,
            'template_type' => $templateType,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
            'status' => LogStatus::Success,
            'sent_at' => now(),
        ]);
    }

    /**
     * Determine the template type from the mailable instance.
     */
    private function determineTemplateType(?string $mailableClass): EmailTemplate
    {
        if (!$mailableClass) {
            return EmailTemplate::OrderConfirmation;
        }

        $className = class_basename($mailableClass);

        return match ($className) {
            'OrderConfirmation' => EmailTemplate::OrderConfirmation,
            'OrderStatusUpdate' => EmailTemplate::OrderStatus,
            'OrderShipped' => EmailTemplate::OrderShipped,
            'WelcomeEmail' => EmailTemplate::Welcome,
            'OtpEmail' => EmailTemplate::Otp,
            'RefundProcessed'    => EmailTemplate::RefundProcessed,
            'RefundStatusUpdate' => EmailTemplate::RefundProcessed,
            'AbandonedCartReminder' => EmailTemplate::AbandonedCart,
            'NewsletterConfirmation' => EmailTemplate::NewsletterConfirm,
            'PasswordReset' => EmailTemplate::PasswordReset,
            'ContactReply' => EmailTemplate::ContactReply,
            default => EmailTemplate::OrderConfirmation,
        };
    }
}