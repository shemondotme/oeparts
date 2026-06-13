<?php

namespace App\Listeners;

use App\Events\PartInquiryReceived;
use App\Models\Admin;
use App\Notifications\PartInquiryNotification;

class NotifyAdminOfPartInquiry
{
    public function handle(PartInquiryReceived $event): void
    {
        $admins = Admin::where('is_active', true)->get();

        foreach ($admins as $admin) {
            $admin->notify(new PartInquiryNotification(
                $event->productId,
                $event->oemNumber,
                $event->customerEmail,
                $event->message
            ));
        }
    }
}
