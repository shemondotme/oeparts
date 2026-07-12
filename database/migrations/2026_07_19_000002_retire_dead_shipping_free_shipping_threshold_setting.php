<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Shipping engine chunk (§5v carry-forward): shipping.free_shipping_threshold
 * was a saved, editable global setting, but the real mechanism has always
 * been a per-ShippingMethod free_shipping_threshold DB column (admin-editable
 * on the Shipping Zones page) — confirmed the only live consumer anywhere in
 * checkout/cart. The global setting was never read. Retired outright (no
 * value to carry — the per-method column is a distinct, already-populated
 * data source, not a migration target). Idempotent + reversible (rule #42);
 * single-group op (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        $deleted = DB::table('settings')
            ->where('group', 'shipping')
            ->where('key', 'free_shipping_threshold')
            ->delete();

        if ($deleted > 0) {
            Cache::forget('settings.shipping');
        }
    }

    public function down(): void
    {
        // Nothing safe to restore — this knob never drove real behavior.
    }
};
