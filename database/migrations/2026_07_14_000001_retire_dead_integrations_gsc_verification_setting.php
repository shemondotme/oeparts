<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * integrations.gsc_verification was a pure duplicate of seo.google_verification
 * (SEOSettings), which is the field layouts/app.blade.php actually renders as
 * <meta name="google-site-verification">. The integrations.* copy was never
 * read anywhere. Carry any operator-entered legacy value across (same
 * existing-target-wins logic as 2026_07_12_000001's tax VAT number fix)
 * before retiring the orphan, then remove the now-dead field from
 * IntegrationsSettings. Idempotent + reversible (rule #42); single-row op
 * (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('settings')->where('group', 'integrations')->where('key', 'gsc_verification')->first();

        if ($legacy === null) {
            return;
        }

        $seoRow = DB::table('settings')->where('group', 'seo')->where('key', 'google_verification')->first();
        $legacyValue = (string) ($legacy->value ?? '');

        if ($seoRow !== null && trim((string) $seoRow->value) === '' && trim($legacyValue) !== '') {
            DB::table('settings')->where('id', $seoRow->id)->update(['value' => $legacyValue]);
        }

        DB::table('settings')->where('id', $legacy->id)->delete();

        Cache::forget('settings.integrations');
        Cache::forget('settings.seo');
    }

    public function down(): void
    {
        // Nothing safe to restore — the legacy orphan row is intentionally gone.
    }
};
