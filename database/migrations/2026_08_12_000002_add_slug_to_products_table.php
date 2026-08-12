<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product detail pages (/parts/{oem}/{id}-{slug}) need a cosmetic,
 * SEO-friendly URL segment. Deliberately nullable and NOT unique: lookup
 * is always by the numeric {id} half of the route segment — the slug is
 * decorative, not a lookup key, so two products colliding on the same
 * base slug is harmless (ProductSlugService::generate()).
 *
 * Idempotent + reversible (rule #42).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'slug')) {
                $table->string('slug', 220)->nullable()->after('normalized_oem');
            }
            if (! Schema::hasIndex('products', ['slug'])) {
                $table->index('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasIndex('products', ['slug'])) {
                $table->dropIndex(['slug']);
            }
            if (Schema::hasColumn('products', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
