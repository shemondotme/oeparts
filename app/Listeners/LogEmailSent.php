<?php

namespace App\Listeners;

use App\Enums\EmailTemplate;
use App\Enums\LogStatus;
use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

class LogEmailSent
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        // __laravel_mailable holds the fully-qualified class name of the Mailable
        $mailableClass = $event->data['__laravel_mailable'] ?? null;

        // getTo() returns Symfony\Component\Mime\Address[]
        $to = $message->getTo();
        $toEmail = $to ? collect($to)->first()?->getAddress() : null;

        if (!$toEmail) {
            return;
        }

        $templateType = $this->determineTemplateType($mailableClass);
        $subject = $message->getSubject() ?? '';

        // $event->data exposes the Mailable's public properties directly
        $relatedId = null;
        $relatedType = null;

        $order = $event->data['order'] ?? null;
        if ($order instanceof \App\Models\Order) {
            $relatedId   = $order->id;
            $relatedType = 'order';
        }

        try {
            EmailLog::create([
                'to_email' => $toEmail,
                'subject' => $subject,
                'template_type' => $templateType,
                'related_id' => $relatedId,
                'related_type' => $relatedType,
                'status' => LogStatus::Success,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log email sent', [
                'to_email' => $toEmail,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function determineTemplateType(?string $mailableClass): EmailTemplate
    {
        if (!$mailableClass) {
            return EmailTemplate::Other;
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
            'PartInquiryStatusUpdate' => EmailTemplate::PartInquiryStatus,
            // Honest catch-all — unknown mailables were misfiled as order
            // confirmations, which poisons the log's usefulness.
            default => EmailTemplate::Other,
        };
    }
}