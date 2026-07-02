<?php

namespace App\Support;

use App\Models\Admin;
use Filament\Notifications\Notification;

/**
 * Sends Filament database notifications (the topbar bell) to admins by role.
 * Callers wrap invocations in try/catch — an alert must never break the CRUD
 * flow that triggered it.
 */
class AdminNotifier
{
    /**
     * Deliver a notification to every admin holding any of $roles. Pass ['*']
     * to reach all admins. No-op when nobody matches.
     */
    public static function toRoles(array $roles, Notification $notification): void
    {
        $recipients = Admin::query()
            ->when(
                ! in_array('*', $roles, true),
                fn ($query) => $query->whereHas('roles', fn ($r) => $r->whereIn('name', $roles)),
            )
            ->get();

        if ($recipients->isNotEmpty()) {
            $notification->sendToDatabase($recipients, isEventDispatched: true);
        }
    }
}
