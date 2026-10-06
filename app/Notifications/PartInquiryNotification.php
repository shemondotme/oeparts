<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Resources\PartInquiryResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartInquiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $partInquiryId,
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
            ->line("From: {$this->customerEmail}")
            ->when($this->message, fn ($mail) => $mail->line("Message: {$this->message}"));
    }

    /** Bell-visible (data.format = 'filament') — see ContactMessageNotification::toDatabase(). */
    public function toDatabase(object $notifiable): array
    {
        return [
            ...FilamentNotification::make()
                ->title('New part inquiry')
                ->body($this->oemNumber.' — '.$this->customerEmail)
                ->icon('heroicon-o-magnifying-glass')
                ->iconColor('info')
                ->viewData(['push_topic' => 'part_inquiry'])
                ->actions([
                    Action::make('view')
                        ->label('View inquiry')
                        ->url(PartInquiryResource::getUrl('view', ['record' => $this->partInquiryId], panel: 'admin'))
                        ->markAsRead(),
                ])
                ->getDatabaseMessage(),
            ...$this->toArray($notifiable),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'part_inquiry',
            'part_inquiry_id' => $this->partInquiryId,
            'oem_number' => $this->oemNumber,
            'customer_email' => $this->customerEmail,
            'message' => $this->message,
        ];
    }
}
