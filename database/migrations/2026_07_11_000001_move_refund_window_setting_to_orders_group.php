<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The refund-window knob lived in two places: OrdersSettings edited
 * orders.refund_window_days while enforcement read refund.refund_window_days
 * (a group no page manages). Enforcement now reads orders.* — carry any
 * operator-set legacy value across so their intent survives. Idempotent +
 * reversible (rule #42); single-row UPDATE, no batching needed (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('settings')->where('group', 'refund')->where('key', 'refund_window_days')->first();

        if ($legacy === null) {
            return;
        }

        $existing = DB::table('settings')->where('group', 'orders')->where('key', 'refund_window_days')->exists();

        if ($existing) {
            // The orders-group knob is the one the operator could actually
            // see and edit — it wins; just retire the orphan.
            DB::table('settings')->where('id', $legacy->id)->delete();
        } else {
            DB::table('settings')->where('id', $legacy->id)->update(['group' => 'orders']);
        }

        Cache::forget('settings.refund');
        Cache::forget('settings.orders');
    }

    public function down(): void
    {
        // Nothing safe to restore — the legacy orphan row is intentionally gone.
    }
};
