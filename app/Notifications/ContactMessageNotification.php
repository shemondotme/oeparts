<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Resources\ContactMessageResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $subject,
        public readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Contact Message: {$this->subject}")
            ->line("From: {$this->name} ({$this->email})")
            ->line("Subject: {$this->subject}")
            ->line('Message:')
            ->line($this->message);
    }

    /**
     * Filament's bell only renders rows carrying data.format = 'filament' — the
     * plain toArray() shape alone was invisible there, so a customer's message
     * never showed up in the panel (and could not be pushed to admin devices).
     * The original keys are kept alongside Filament's.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            ...FilamentNotification::make()
                ->title('New contact message')
                ->body($this->name.' — '.$this->subject)
                ->icon('heroicon-o-envelope')
                ->iconColor('info')
                ->viewData(['push_topic' => 'contact_message'])
                ->actions([
                    Action::make('view')
                        ->label('View messages')
                        ->url(ContactMessageResource::getUrl('index', panel: 'admin'))
                        ->markAsRead(),
                ])
                ->getDatabaseMessage(),
            ...$this->toArray($notifiable),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'contact_message',
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
        ];
    }
}
