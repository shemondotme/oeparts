<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * tax.company_vat_number was a "knob that lies": TaxSettings exposed it with
 * helper text claiming "printed on generated customer invoices", but
 * InvoiceService has only ever read company.vat_number (CompanySettings) —
 * the tax.* field was never wired to anything. An operator who filled in
 * ONLY the tax.* field (as its own helper text told them to) would have a
 * blank seller VAT number on every invoice with no indication anything was
 * wrong. Carry any operator-entered legacy value across (same
 * existing-target-wins logic as 2026_07_11_000001's refund-window fix)
 * before retiring the orphan, then remove the now-dead field from
 * TaxSettings. Idempotent + reversible (rule #42); single-row op (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('settings')->where('group', 'tax')->where('key', 'company_vat_number')->first();

        if ($legacy === null) {
            return;
        }

        $companyRow = DB::table('settings')->where('group', 'company')->where('key', 'vat_number')->first();
        $legacyValue = (string) ($legacy->value ?? '');

        if ($companyRow !== null && trim((string) $companyRow->value) === '' && trim($legacyValue) !== '') {
            // The operator's real (never-applied) VAT number was sitting in
            // the dead knob — surface it on the field invoices actually read.
            DB::table('settings')->where('id', $companyRow->id)->update(['value' => $legacyValue]);
        }

        DB::table('settings')->where('id', $legacy->id)->delete();

        Cache::forget('settings.tax');
        Cache::forget('settings.company');
    }

    public function down(): void
    {
        // Nothing safe to restore — the legacy orphan row is intentionally gone.
    }
};
