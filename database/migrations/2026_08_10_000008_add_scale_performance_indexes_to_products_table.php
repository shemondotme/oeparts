<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Found during a performance investigation after the catalog grew to
 * ~100k products:
 *
 * - ProductResource's admin table defaults to ->defaultSort('created_at',
 *   'desc') (app/Filament/Resources/ProductResource.php), but no prior
 *   migration ever indexed products.created_at — the default (unfiltered)
 *   admin list-page load forces MySQL to filesort the entire table before
 *   applying LIMIT for pagination.
 * - The out-of-stock navigation badge counts
 *   where('is_active', true)->where('is_in_stock', false) — only single-
 *   column indexes exist on each, no composite covering both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['is_active', 'is_in_stock']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['is_active', 'is_in_stock']);
        });
    }
};
