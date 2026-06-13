<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('condition_id')
                ->nullable()
                ->after('condition')
                ->constrained('conditions')
                ->nullOnDelete();
        });

        DB::statement("
            UPDATE products SET condition_id = (
                SELECT id FROM conditions WHERE slug = products.condition
            )
        ");

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['condition_id']);
            $table->dropColumn('condition');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('condition_id')
                ->nullable(false)
                ->change();
            $table->foreign('condition_id')
                ->references('id')
                ->on('conditions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('condition', 50)->nullable()->after('id');
        });

        DB::statement("
            UPDATE products SET condition = (
                SELECT slug FROM conditions WHERE id = products.condition_id
            )
        ");

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['condition_id']);
            $table->dropColumn('condition_id');
        });
    }
};
