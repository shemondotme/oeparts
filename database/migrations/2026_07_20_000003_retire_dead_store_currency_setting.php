<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * store.currency / store.currency_symbol were seeded but never wired to any
 * admin-editable field — StoreSettings.php's own "Store Currency" field is a
 * read-only Placeholder that reads general.currency, not this group. Every
 * real price render (format_price()) already reads general.currency /
 * general.currency_symbol. But 34 call sites across cart, checkout, search,
 * navbar, banner, PaymentService, and CheckoutController were still reading
 * this frozen 'store' pair directly — harmless only because it happened to
 * be seeded to the same default ('EUR'/'€') as general.currency. Since
 * general.currency's admin Select genuinely offers USD/GBP/CHF/PLN/SEK,
 * changing it away from EUR would have silently left the payment charge
 * currency (PaymentService, CheckoutController) and several on-page currency
 * symbols stuck on EUR/€ while format_price() moved to the new currency — a
 * real display/charge mismatch. All 34 call sites were repointed to
 * general.currency(_symbol) in the same change that retires this pair.
 * Idempotent + reversible (rule #42); single-group op (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        $deleted = DB::table('settings')
            ->where('group', 'store')
            ->whereIn('key', ['currency', 'currency_symbol'])
            ->delete();

        if ($deleted > 0) {
            Cache::forget('settings.store');
        }
    }

    public function down(): void
    {
        // Nothing safe to restore — every real reader now points at
        // general.currency(_symbol); reseeding this pair would just
        // resurrect the dead/unreachable duplicate.
    }
};
