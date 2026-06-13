<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartInquiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $productId,
        public readonly string $oemNumber,
        public readonly string $customerEmail,
        public readonly ?string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Part Inquiry: {$this->oemNumber}")
            ->line("OEM Number: {$this->oemNumber}")
            ->line("Product ID: {$this->productId}")
            ->line("From: {$this->customerEmail}")
            ->when($this->message, fn ($mail) => $mail->line("Message: {$this->message}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'part_inquiry',
            'product_id' => $this->productId,
            'oem_number' => $this->oemNumber,
            'customer_email' => $this->customerEmail,
            'message' => $this->message,
        ];
    }
}
