<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specifications/warranty/video are genuinely new data — nothing on the
 * product detail page's PDP-overhaul renders them today because the columns
 * never existed, not because the view withheld them.
 *
 * Idempotent + reversible (rule #42).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'specifications')) {
                $table->json('specifications')->nullable()->after('description');
            }
            if (! Schema::hasColumn('products', 'warranty_months')) {
                $table->unsignedSmallInteger('warranty_months')->nullable()->after('moq');
            }
            if (! Schema::hasColumn('products', 'video_url')) {
                $table->string('video_url', 500)->nullable()->after('warranty_months');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['specifications', 'warranty_months', 'video_url'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
