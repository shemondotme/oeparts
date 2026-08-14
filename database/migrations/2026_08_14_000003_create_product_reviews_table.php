<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Open submission (no verified-purchase gate), admin-moderated: rows are
 * created as 'pending' and only ever reach the storefront once a Filament
 * admin flips status to 'approved' via ReviewResource. The composite
 * [product_id, status] index is the exact shape of the PDP's "approved
 * reviews for this product" query and its average-rating aggregate.
 *
 * Idempotent + reversible (rule #42).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('reviewer_name', 100);
                $table->string('title', 150)->nullable();
                $table->text('comment');
                $table->tinyInteger('rating');
                $table->string('status', 20)->default('pending'); // pending|approved|rejected
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['product_id', 'status']);
                $table->index('status');
            });
        }

        if (Schema::getConnection()->getDriverName() !== 'sqlite' && ! $this->constraintExists()) {
            DB::statement('ALTER TABLE product_reviews ADD CONSTRAINT product_reviews_rating_range CHECK (rating BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite' && $this->constraintExists()) {
            DB::statement('ALTER TABLE product_reviews DROP CONSTRAINT product_reviews_rating_range');
        }

        Schema::dropIfExists('product_reviews');
    }

    private function constraintExists(): bool
    {
        $row = DB::selectOne(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_reviews'
               AND CONSTRAINT_NAME = 'product_reviews_rating_range'"
        );

        return $row !== null;
    }
};
