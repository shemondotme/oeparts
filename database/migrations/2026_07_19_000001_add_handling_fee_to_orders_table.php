<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping engine chunk (§5v carry-forward): shipping.handling_fee was a
 * saved, editable setting that was never applied to any order total. Like
 * urgent_processing_fee, the charged amount must be snapshotted onto the
 * order at creation time (settings can change later; historical orders must
 * keep the fee that was actually charged). Idempotent + reversible (rule
 * #42); single-table op (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'handling_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('handling_fee', 10, 2)->default('0.00')->after('urgent_processing_fee');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'handling_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('handling_fee');
            });
        }
    }
};
