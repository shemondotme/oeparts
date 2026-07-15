<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ShippingZone::$casts had 'name' => 'array' on a plain varchar column (per
 * the model's own comment: "existing rows were written through it... DB
 * values are JSON-quoted strings"). That cast only affects Eloquent
 * attribute hydration/dehydration — it does NOT affect query builder
 * where() clauses, so ShippingZone::where('name', 'Europe') can never match
 * a raw stored value of '"Europe"' (with literal quotes). Confirmed live:
 * this dev DB's one real zone had exactly that corruption (raw value
 * '"Europe"', decoding correctly to 'Europe' only through the cast).
 *
 * This migration un-quotes every already-corrupted row (idempotent — a row
 * whose raw value isn't valid JSON, or isn't a JSON string, is left alone)
 * so the column holds a plain string; the array cast is then removed from
 * the model in the same commit, since post-migration every row is a normal
 * varchar value needing no cast at all. The form only ever collected a
 * single plain TextInput value here (no AdminUi::translatableTabs()) — this
 * was never a genuine multilang field, just data debt from an earlier bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('shipping_zones')->select('id', 'name')->orderBy('id')->each(function (object $zone): void {
            $decoded = json_decode((string) $zone->name, true);

            if (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) {
                DB::table('shipping_zones')->where('id', $zone->id)->update(['name' => $decoded]);
            }
        });
    }

    public function down(): void
    {
        DB::table('shipping_zones')->select('id', 'name')->orderBy('id')->each(function (object $zone): void {
            DB::table('shipping_zones')->where('id', $zone->id)->update(['name' => json_encode($zone->name)]);
        });
    }
};
