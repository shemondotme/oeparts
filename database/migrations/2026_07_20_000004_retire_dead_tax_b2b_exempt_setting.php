<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * tax.b2b_exempt_on_valid_vat backed the self-service "I am ordering as a
 * business" toggle on the storefront checkout (step2.blade.php), which read
 * a customer-entered EU VAT number, validated it live via VIES, and — when
 * valid — zeroed the order's VAT via CheckoutService::calculateVat(). That
 * whole checkout-side B2B flow has been removed (B2B ordering will get its
 * own dedicated system later); no code reads this key anymore. The sibling
 * key tax.vat_validation_enabled is NOT touched here — it still gates the
 * generic /api/validate-vat endpoint (VatValidationController), which is
 * independent, still-functioning infrastructure the future B2B system can
 * reuse. Idempotent + reversible (rule #42); single-group op (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        $deleted = DB::table('settings')
            ->where('group', 'tax')
            ->where('key', 'b2b_exempt_on_valid_vat')
            ->delete();

        if ($deleted > 0) {
            Cache::forget('settings.tax');
        }
    }

    public function down(): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'tax', 'key' => 'b2b_exempt_on_valid_vat'],
            ['value' => '1', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()]
        );
        Cache::forget('settings.tax');
    }
};
