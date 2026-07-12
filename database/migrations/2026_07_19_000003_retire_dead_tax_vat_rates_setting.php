<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tax engine chunk (§5v carry-forward): tax.vat_rates (a per-country VAT
 * override map) was saved but never consulted anywhere — all real VAT math
 * uses the flat tax.default_vat_rate only. Real destination-based/OSS VAT is
 * a legal-compliance decision, not a UI-wiring task, so this is retired
 * rather than built; a future compliance-driven chunk can reintroduce it
 * once the business has confirmed what's actually required. Idempotent +
 * reversible (rule #42); single-group op (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        $deleted = DB::table('settings')
            ->where('group', 'tax')
            ->where('key', 'vat_rates')
            ->delete();

        if ($deleted > 0) {
            Cache::forget('settings.tax');
        }
    }

    public function down(): void
    {
        // Nothing safe to restore — this knob never drove real behavior.
    }
};
