<?php

namespace App\Services;

use App\Jobs\SendAbandonedCartEmail;
use App\Models\AbandonedCart;

/**
 * Single path for sending a cart-recovery email — used by the hourly
 * ProcessAbandonedCarts command, the AbandonedCartResource row action,
 * and the dashboard AbandonedCartWidget, so "recovery sent" always means
 * the same thing (queued mailable + recovery_email_sent flag).
 */
class CartRecoveryService
{
    /**
     * Queue the recovery email and flag the record.
     *
     * @return bool false when the record has no reachable email address
     */
    public function send(AbandonedCart $record): bool
    {
        $email = $record->guest_email ?? $record->user?->email;

        if (! $email) {
            return false;
        }

        dispatch(new SendAbandonedCartEmail(
            email: $email,
            cartSnapshot: $record->cart_snapshot,
            customerName: $record->user?->name ?? ($record->cart_snapshot['customer_name'] ?? null),
            locale: $record->user?->preferred_locale ?? 'en',
        ));

        $record->update(['recovery_email_sent' => true]);

        return true;
    }
}
