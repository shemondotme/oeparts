<?php

namespace App\Listeners;

use App\Events\RefundRequested;
use App\Models\Admin;
use App\Notifications\RefundRequestedNotification;

class NotifyAdminOfRefund
{
    public function handle(RefundRequested $event): void
    {
        $admins = Admin::where('is_active', true)->get();

        foreach ($admins as $admin) {
            $admin->notify(new RefundRequestedNotification($event->order, $event->reason));
        }
    }
}
