<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * cart.checkout_timeout_minutes was a pure duplicate of checkout.timeout_minutes
 * (CheckoutSettings) — same range (5-120), same default (30), but only the
 * checkout.* copy is actually read (CheckoutService::start()/CheckoutController's
 * countdown display). The cart.* copy was never consulted anywhere. Carry any
 * operator-entered legacy value across (same existing-target-wins logic as
 * 2026_07_12_000001's tax VAT number fix) before retiring the orphan, then
 * remove the now-dead field from CartSettings. Idempotent + reversible
 * (rule #42); single-row op (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('settings')->where('group', 'cart')->where('key', 'checkout_timeout_minutes')->first();

        if ($legacy === null) {
            return;
        }

        $checkoutRow = DB::table('settings')->where('group', 'checkout')->where('key', 'timeout_minutes')->first();
        $legacyValue = (string) ($legacy->value ?? '');

        if ($checkoutRow !== null && trim((string) $checkoutRow->value) === '' && trim($legacyValue) !== '') {
            DB::table('settings')->where('id', $checkoutRow->id)->update(['value' => $legacyValue]);
        }

        DB::table('settings')->where('id', $legacy->id)->delete();

        Cache::forget('settings.cart');
        Cache::forget('settings.checkout');
    }

    public function down(): void
    {
        // Nothing safe to restore — the legacy orphan row is intentionally gone.
    }
};
