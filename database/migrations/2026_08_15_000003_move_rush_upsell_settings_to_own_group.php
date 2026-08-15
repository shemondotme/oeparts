<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rush Processing Upsell moves from the Store & Commerce category
 * (checkout.urgent_processing_*) to Marketing — editorially it's an
 * upsell/marketing lever, not a checkout mechanic, and putting it on the
 * Marketing settings page means it needs its own settings group (a page
 * can only manage whole groups, never a key-subset of one — no precedent
 * anywhere in this codebase for splitting a group across two pages).
 *
 * Copies (not deletes-then-recreates) each of the 4 rows to
 * group='rush_upsell' with the SAME key names and values, so any
 * operator-set fee/label/description survives untouched, then removes
 * the old checkout.* rows. Same idempotent/reversible shape as
 * 2026_08_15_000002's group-adjacent rename — existing-target-wins if a
 * rush_upsell row somehow already exists (defensive; none should).
 */
return new class extends Migration
{
    private const KEYS = ['urgent_processing_enabled', 'urgent_processing_fee', 'urgent_processing_label', 'urgent_processing_description'];

    public function up(): void
    {
        foreach (self::KEYS as $key) {
            $legacy = DB::table('settings')->where('group', 'checkout')->where('key', $key)->first();

            if ($legacy === null) {
                continue;
            }

            $existing = DB::table('settings')->where('group', 'rush_upsell')->where('key', $key)->exists();

            if ($existing) {
                DB::table('settings')->where('id', $legacy->id)->delete();
            } else {
                DB::table('settings')->where('id', $legacy->id)->update(['group' => 'rush_upsell']);
            }
        }

        Cache::forget('settings.checkout');
        Cache::forget('settings.rush_upsell');
    }

    public function down(): void
    {
        foreach (self::KEYS as $key) {
            DB::table('settings')->where('group', 'rush_upsell')->where('key', $key)->update(['group' => 'checkout']);
        }

        Cache::forget('settings.checkout');
        Cache::forget('settings.rush_upsell');
    }
};
