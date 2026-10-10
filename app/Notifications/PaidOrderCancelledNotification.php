<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An order that already carries the customer's money (captured, transferred, or
 * held on their card) has been cancelled. Cancelling does not move any money —
 * somebody has to refund the transfer / release the card hold — so staff are
 * told straight away instead of the customer waiting on a refund nobody knows
 * is owed.
 */
class PaidOrderCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Refund due: paid order {$this->order->order_number} was cancelled")
            ->view(['emails.admin-notification', 'emails.admin-notification-text'], [
                'eyebrow' => 'FINANCE · REFUND DUE',
                'label' => 'Paid order cancelled',
                'heading' => 'Order '.$this->order->order_number,
                'rows' => [
                    'Order' => $this->order->order_number,
                    'Customer' => $this->order->user?->email ?? $this->order->guest_email ?? 'Guest',
                    'Payment method' => $this->order->payment_method?->value ?? '—',
                    'Amount to return' => format_price($this->order->grand_total),
                ],
                'bodyLabel' => 'Action needed',
                'body' => 'This order was cancelled after the customer had paid. Cancelling does not refund anything: return the money (bank refund, or release the card hold in the payment gateway) and note it on the order.',
                'actionUrl' => OrderResource::getUrl('view', ['record' => $this->order->id], panel: 'admin'),
                'actionLabel' => 'Open order',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'paid_order_cancelled',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'amount' => (string) $this->order->grand_total,
        ];
    }
}
