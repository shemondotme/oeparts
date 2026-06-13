<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly ?string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customerEmail = $this->order->user?->email ?? 'Guest';

        return (new MailMessage)
            ->subject("Refund Requested for Order {$this->order->order_number}")
            ->line("Order Number: {$this->order->order_number}")
            ->line("Customer: {$customerEmail}")
            ->line("Order Total: {$this->order->total}")
            ->when($this->reason, fn ($mail) => $mail->line("Reason: {$this->reason}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'refund_requested',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'reason' => $this->reason,
        ];
    }
}
