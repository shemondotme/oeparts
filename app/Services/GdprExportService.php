<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\LoginLog;
use App\Models\NewsletterSubscriber;
use App\Models\PartInquiry;
use App\Models\SearchLog;
use App\Models\User;

class GdprExportService
{
    /**
     * Build the complete set of personal data this codebase holds for a
     * customer, for a GDPR data-portability export. Some related tables
     * (newsletter subscribers, part inquiries) have no user_id column and
     * are matched by email only.
     */
    public function exportForUser(User $user): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => $user->is_active,
                'preferred_locale' => $user->preferred_locale,
                'timezone' => $user->timezone,
                'prefers_order_notifications' => $user->prefers_order_notifications,
                'prefers_email_notifications' => $user->prefers_email_notifications,
                'prefers_promotional_emails' => $user->prefers_promotional_emails,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ],
            'addresses' => $user->addresses->toArray(),
            'orders' => $user->orders()->with(['items', 'payments'])->get()->toArray(),
            'carts' => $user->carts()->with('items')->get()->toArray(),
            'refund_requests' => $user->refundRequests->toArray(),
            'search_logs' => SearchLog::where('user_id', $user->id)->get()->toArray(),
            'login_logs' => LoginLog::where('user_id', $user->id)
                ->orWhere('email', $user->email)
                ->get()
                ->toArray(),
            // ContactMessage::sender() references a 'sender_id' column that
            // doesn't exist anywhere in the contact_messages schema (confirmed
            // against the migration) — the table is email-only, matching here.
            'contact_messages' => ContactMessage::where('email', $user->email)
                ->get()
                ->toArray(),
            'newsletter_subscription' => NewsletterSubscriber::where('email', $user->email)->first()?->toArray(),
            'part_inquiries' => PartInquiry::where('email', $user->email)->get()->toArray(),
        ];
    }
}
