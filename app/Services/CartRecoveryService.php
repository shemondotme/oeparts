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
     * Queue the recovery email. The record's recovery_email_sent flag is set
     * by the job itself, only once the send actually succeeds — not here,
     * since dispatch() only guarantees the job was queued, not delivered.
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
            abandonedCartId: $record->id,
            email: $email,
            cartSnapshot: $record->cart_snapshot,
            customerName: $record->user?->name ?? ($record->cart_snapshot['customer_name'] ?? null),
            locale: $record->user?->preferred_locale ?? 'en',
        ));

        return true;
    }
}
