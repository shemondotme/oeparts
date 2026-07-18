<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The address form has always required and validated a "state / province"
 * field, but user_addresses never had a column for it — every submission
 * silently discarded the value (Expand-only, rule #43; idempotent +
 * reversible, rule #42).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('user_addresses', 'state')) {
                $table->string('state', 100)->nullable()->after('city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('user_addresses', 'state')) {
                $table->dropColumn('state');
            }
        });
    }
};
