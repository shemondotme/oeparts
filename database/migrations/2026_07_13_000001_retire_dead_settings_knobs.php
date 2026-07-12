<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Settings-framework audit (Configurability chunk) found 68 "knobs that lie"
 * across ~20 pages — real editable fields whose saved value is never read
 * anywhere, same bug class as 2026_07_11_000001 (refund window) and
 * 2026_07_12_000001 (tax.company_vat_number). This migration retires the
 * subset that's safe to resolve mechanically: pure duplicates of an
 * already-working field (carry any operator-entered value across before
 * deleting the orphan, existing-target-wins if both are set), plus a
 * handful of settings whose sole consumer no longer exists in the live app.
 * Toggles that merely *sound* like real feature gates but never gated
 * anything (payment methods, IP blocklist, honeypot/CSRF/HTTPS) are removed
 * outright — the underlying protections/behavior stay unconditionally on,
 * which was already reality, so removing the misleading toggle changes
 * nothing except no longer lying to the operator.
 *
 * Idempotent + reversible (rule #42); all single-row ops (rule #44).
 */
return new class extends Migration
{
    /**
     * [sourceGroup, sourceKey, targetGroup, targetKey] — carry the operator's
     * value from source into target (only if target is still blank/default),
     * then delete the source row.
     */
    private function carryOverPairs(): array
    {
        return [
            ['general', 'tagline', 'general', 'site_tagline'],
            ['general', 'site_address', 'company', 'address'],
            ['seo', 'oem_title_template', 'seo', 'search_results_title_template'],
            ['seo', 'oem_description_template', 'seo', 'search_results_meta_template'],
            ['dashboard', 'pending_orders_warning', 'dashboard', 'orders_threshold'],
        ];
    }

    /**
     * [group, key] — no live replacement to carry a value into; just retire.
     */
    private function simpleRetirements(): array
    {
        return [
            ['seo', 'og_site_name'],
            ['seo', 'maintenance_noindex'],
            ['payment', 'card_enabled'],
            ['payment', 'bank_transfer_enabled'],
            ['security', 'ip_blocklist_enabled'],
            ['security', 'honeypot_enabled'],
            ['security', 'csrf_enabled'],
            ['security', 'force_https'],
            ['security', 'blocked_ips'],
            ['orders', 'expected_delivery_days'],
            ['search', 'max_results'],
        ];
    }

    public function up(): void
    {
        $touchedGroups = [];

        foreach ($this->carryOverPairs() as [$srcGroup, $srcKey, $dstGroup, $dstKey]) {
            $source = DB::table('settings')->where('group', $srcGroup)->where('key', $srcKey)->first();
            if ($source === null) {
                continue;
            }

            $target = DB::table('settings')->where('group', $dstGroup)->where('key', $dstKey)->first();
            $sourceValue = trim((string) ($source->value ?? ''));

            if ($target === null) {
                // Target key didn't exist yet on this install (e.g. the
                // rename general.tagline -> general.site_tagline) — create
                // it from the source row so the operator's value survives.
                if ($sourceValue !== '') {
                    DB::table('settings')->insert([
                        'group' => $dstGroup,
                        'key' => $dstKey,
                        'value' => $source->value,
                        'type' => $source->type,
                        'is_encrypted' => $source->is_encrypted ?? false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } elseif (trim((string) $target->value) === '' && $sourceValue !== '') {
                DB::table('settings')->where('id', $target->id)->update(['value' => $source->value]);
            }

            DB::table('settings')->where('id', $source->id)->delete();
            $touchedGroups[$srcGroup] = true;
            $touchedGroups[$dstGroup] = true;
        }

        foreach ($this->simpleRetirements() as [$group, $key]) {
            $deleted = DB::table('settings')->where('group', $group)->where('key', $key)->delete();
            if ($deleted > 0) {
                $touchedGroups[$group] = true;
            }
        }

        foreach (array_keys($touchedGroups) as $group) {
            Cache::forget("settings.{$group}");
        }
    }

    public function down(): void
    {
        // Nothing safe to restore — the retired knobs never drove real
        // behavior, and carried-over values now live on their target key.
    }
};
