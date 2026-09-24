<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the external URL a ProductImage was fetched from (bulk CSV import
 * only — a direct FileUpload has no URL to record, so this stays null for
 * those rows). Lets ProductImportService detect "we already fetched this
 * exact URL for this product" on a re-imported CSV and skip it, the same way
 * ProductCrossReference's normalized_cross_oem column makes cross-reference
 * import idempotent — without it, re-uploading the same file would re-
 * download and re-store a duplicate image every time.
 *
 * Idempotent + reversible (rule #42) — via SafeSchema (see
 * add_slug_to_products_table for why a bare hasColumn() pre-check isn't
 * enough on its own for a table that real live upgrades pass through).
 */
return new class extends Migration
{
    public function up(): void
    {
        SafeSchema::table('add_source_url_to_product_images_table', 'product_images', function (Blueprint $table) {
            if (! Schema::hasColumn('product_images', 'source_url')) {
                $table->string('source_url', 2048)->nullable()->after('path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product_images', 'source_url')) {
            return;
        }

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('source_url');
        });
    }
};
