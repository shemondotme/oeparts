<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * orders.urgent_processing_enabled/fee were the original Rush Processing
 * Upsell fields, superseded by checkout.urgent_processing_* when the
 * feature moved to CheckoutSettings — OrdersSettings.php's own form
 * comment confirms the knobs were removed from that page, and neither key
 * is read anywhere in the app (confirmed via grep). They sat as orphaned
 * rows until now, harmless on their own, but the Store & Commerce settings
 * reorg (Phase 4) merges the 'orders' and 'checkout' groups onto ONE
 * Livewire component whose fillForm()/getFactoryDefaults() flatten every
 * group's rows into a single key-keyed array — a same-named key surviving
 * in two merged groups silently collides (last-query-order wins), which
 * would have made saves on the real checkout.urgent_processing_* fields
 * intermittently write to/read from the wrong group. No legacy value to
 * carry forward (unlike 2026_07_12_000001's VAT number case) — these two
 * rows were never operator-editable after the field moved to Checkout
 * Settings, so both are simply retired.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'orders')
            ->whereIn('key', ['urgent_processing_enabled', 'urgent_processing_fee'])
            ->delete();

        Cache::forget('settings.orders');
    }

    public function down(): void
    {
        // Nothing safe to restore — the orphan rows are intentionally gone.
    }
};
