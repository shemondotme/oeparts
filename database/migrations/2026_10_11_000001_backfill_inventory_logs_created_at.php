<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * inventory_logs.created_at was NULL on every row ever written: the model has
 * $timestamps = false, created_at isn't fillable, and nothing stamped it
 * (InventoryLog now stamps itself in a creating() hook). A row with no age can
 * never fall under a retention policy, so the table could only ever grow.
 *
 * Stamp the existing NULL rows with the time of THIS migration, never a guessed
 * older date. The true age is unknown, so the only safe choice is "treat it as
 * new": these rows then age out one full retention period from now, and nothing
 * is ever pruned earlier than the operator's retention allows.
 *
 * Done in id windows because the table can hold millions of rows after large
 * CSV imports; one UPDATE would lock it for minutes.
 */
return new class extends Migration
{
    private const WINDOW = 50_000;

    public function up(): void
    {
        if (! Schema::hasTable('inventory_logs') || ! Schema::hasColumn('inventory_logs', 'created_at')) {
            return;
        }

        $max = (int) DB::table('inventory_logs')->whereNull('created_at')->max('id');
        if ($max === 0) {
            return;
        }

        $stamp = now();
        $from = (int) DB::table('inventory_logs')->whereNull('created_at')->min('id');

        for ($low = $from; $low <= $max; $low += self::WINDOW) {
            DB::table('inventory_logs')
                ->whereNull('created_at')
                ->whereBetween('id', [$low, $low + self::WINDOW - 1])
                ->update(['created_at' => $stamp]);
        }
    }

    public function down(): void
    {
        // Irreversible on purpose: the original value was NULL for every row, which
        // is indistinguishable from "stamped by this migration" afterwards.
    }
};
