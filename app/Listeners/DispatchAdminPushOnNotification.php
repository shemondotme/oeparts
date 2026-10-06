<?php

namespace App\Listeners;

use App\Models\Admin;
use App\Services\Push\AdminPushService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

/**
 * Single choke point for admin push: every path that writes a bell
 * notification (Filament's sendToDatabase, AdminNotificationService, any
 * future Notification class using the database channel) fires NotificationSent,
 * so new notification kinds get push without touching this class.
 */
class DispatchAdminPushOnNotification
{
    public function __construct(private readonly AdminPushService $push) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database'
            || ! $event->notifiable instanceof Admin
            || ! $event->response instanceof DatabaseNotification) {
            return;
        }

        try {
            $this->push->handleStored($event->notifiable, $event->response);
        } catch (\Throwable $e) {
            // Push is a convenience on top of the bell — it must never break the
            // flow that raised the notification (checkout, refunds, jobs…).
            Log::error('DispatchAdminPushOnNotification failed', [
                'admin' => $event->notifiable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
