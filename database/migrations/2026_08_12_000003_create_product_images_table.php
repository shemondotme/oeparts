<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product image gallery (one featured/primary image + additional
 * gallery images) for the new detail pages — falls back to the product's
 * manufacturer logo when no image has been uploaded. thumbnail_path/
 * medium_path are filled asynchronously by ProcessProductImage; both stay
 * nullable so the gallery degrades to the original upload (never breaks)
 * on GD-unavailable environments or before the job has run.
 *
 * "Exactly one featured image per product" is enforced in the Filament
 * RelationManager, not a DB constraint — SQLite (tests) doesn't support
 * a portable partial-unique-index for this.
 *
 * Idempotent + reversible (rule #42).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_images')) {
            return;
        }

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('thumbnail_path', 255)->nullable();
            $table->string('medium_path', 255)->nullable();
            $table->json('alt_text')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
