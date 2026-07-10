<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand phase (CLAUDE rule #43): orders gain a real FK to carriers so the
 * Carrier module's tracking-URL templates actually drive customer tracking
 * links. The legacy free-text `carrier` column stays for now (Contract in a
 * later release); Order::carrier_name falls back to it for old orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'carrier_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('carrier_id')
                    ->nullable()
                    ->after('carrier')
                    ->constrained('carriers')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'carrier_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('carrier_id');
            });
        }
    }
};
