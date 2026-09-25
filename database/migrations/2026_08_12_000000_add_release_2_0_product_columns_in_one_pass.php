<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every column and index this release adds to `products`, in ONE statement.
 *
 * `products` carries a FULLTEXT (ngram) index, which rules out MySQL's INSTANT
 * and INPLACE algorithms: EVERY ALTER TABLE on it copies the whole table and
 * rebuilds that index. At the real catalog's ~1M rows one such ALTER takes
 * minutes, and the release used to issue five of them from separate migrations
 * (slug column, slug index, and three PDP columns) — measured at roughly 6 minutes
 * EACH on a million-product rehearsal, all of it inside the update's maintenance
 * window. One combined ALTER is one rebuild.
 *
 * The individual migrations that own these columns (add_slug_to_products_table,
 * add_pdp_fields_to_products_table) are unchanged and still run afterwards, but
 * find nothing left to do — so a fresh database, a half-migrated one and an
 * already-migrated one all converge on the same schema. Only the columns/index
 * that are actually missing go into the statement, so a database where some of
 * them already exist (an earlier interrupted update) is handled too.
 *
 * Idempotent + reversible (rule #42).
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = array_values(array_filter(
            ['slug', 'specifications', 'warranty_months', 'video_url'],
            fn (string $column) => ! Schema::hasColumn('products', $column)
        ));
        $needsSlugIndex = ! Schema::hasIndex('products', ['slug']);

        if ($missing === [] && ! $needsSlugIndex) {
            return;
        }

        SafeSchema::table('add_release_2_0_product_columns_in_one_pass', 'products', function (Blueprint $table) use ($missing, $needsSlugIndex) {
            // Column order matches the individual migrations, so the resulting table is
            // identical however it was built. video_url is placed after warranty_months,
            // which MySQL resolves within the same ALTER because it is added just before.
            if (in_array('slug', $missing, true)) {
                $table->string('slug', 220)->nullable()->after('normalized_oem');
            }
            if (in_array('specifications', $missing, true)) {
                $table->json('specifications')->nullable()->after('description');
            }
            if (in_array('warranty_months', $missing, true)) {
                $table->unsignedSmallInteger('warranty_months')->nullable()->after('moq');
            }
            if (in_array('video_url', $missing, true)) {
                $table->string('video_url', 500)->nullable()->after('warranty_months');
            }
            if ($needsSlugIndex) {
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
            foreach (['video_url', 'warranty_months', 'specifications', 'slug'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
