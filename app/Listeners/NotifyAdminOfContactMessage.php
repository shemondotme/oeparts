<?php

namespace App\Listeners;

use App\Events\ContactMessageReceived;
use App\Models\Admin;
use App\Notifications\ContactMessageNotification;

class NotifyAdminOfContactMessage
{
    public function handle(ContactMessageReceived $event): void
    {
        $admins = Admin::where('is_active', true)->get();

        foreach ($admins as $admin) {
            $admin->notify(new ContactMessageNotification(
                $event->name,
                $event->email,
                $event->subject,
                $event->message
            ));
        }
    }
}
