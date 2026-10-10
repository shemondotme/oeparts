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
 * An order that already carried the customer's money has been cancelled. What
 * staff must do depends on what was found (see SettleCancelledPaidOrder).
 */
class PaidOrderCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Money was taken: it has to be returned, then marked processed. */
    public const REFUND_DUE = 'refund_due';

    /** Only a card hold existed and it was released automatically — nothing to do. */
    public const HOLD_RELEASED = 'hold_released';

    /** Only a card hold existed and releasing it failed — release it in the gateway. */
    public const HOLD_RELEASE_FAILED = 'hold_release_failed';

    public function __construct(
        public readonly Order $order,
        public readonly string $outcome = self::REFUND_DUE,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function subject(): string
    {
        return match ($this->outcome) {
            self::HOLD_RELEASED => "Card hold released: order {$this->order->order_number} was cancelled",
            self::HOLD_RELEASE_FAILED => "Action needed: release the card hold for cancelled order {$this->order->order_number}",
            default => "Refund due: paid order {$this->order->order_number} was cancelled",
        };
    }

    private function body(): string
    {
        return match ($this->outcome) {
            self::HOLD_RELEASED => 'This order was cancelled while the customer\'s card payment was only held (never charged). The hold has been released automatically — nothing else to do.',
            self::HOLD_RELEASE_FAILED => 'This order was cancelled while the customer\'s card payment was only held (never charged), but releasing the hold failed. Cancel the payment in the Airwallex dashboard so the money is not left frozen on the customer\'s card. Details are in the application log.',
            default => 'This order was cancelled after the customer had paid. Cancelling does not move any money: a refund request for the full amount was added to Refunds. Return the money (bank refund, or refund the card payment in the payment gateway), then press "Mark as processed" there so the customer is told.',
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->view(['emails.admin-notification', 'emails.admin-notification-text'], [
                'eyebrow' => $this->outcome === self::HOLD_RELEASED ? 'FINANCE · CARD HOLD' : 'FINANCE · REFUND DUE',
                'label' => 'Paid order cancelled',
                'heading' => 'Order '.$this->order->order_number,
                'rows' => [
                    'Order' => $this->order->order_number,
                    'Customer' => $this->order->recipientEmail() ?? 'Guest',
                    'Payment method' => $this->order->payment_method->value,
                    'Amount' => format_price($this->order->grand_total),
                ],
                'bodyLabel' => $this->outcome === self::HOLD_RELEASED ? 'Result' : 'Action needed',
                'body' => $this->body(),
                'actionUrl' => OrderResource::getUrl('view', ['record' => $this->order->id], panel: 'admin'),
                'actionLabel' => 'Open order',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'paid_order_cancelled',
            'outcome' => $this->outcome,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'amount' => (string) $this->order->grand_total,
        ];
    }
}
