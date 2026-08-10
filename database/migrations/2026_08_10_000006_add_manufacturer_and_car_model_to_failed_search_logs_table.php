<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SearchService::logFailedSearch() has always passed manufacturer_id/
 * car_model_id to FailedSearchLog::create() — mirroring the successful-search
 * case in logSearch() — but neither column ever existed on this table, so
 * mass-assignment silently dropped them on every single failed-search log.
 * The filter-scoped search context (which manufacturer/car-model page a
 * zero-result search happened on) was lost for every row, ever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('failed_search_logs', function (Blueprint $table) {
            $table->foreignId('manufacturer_id')->nullable()->after('normalized_query')->constrained('manufacturers')->nullOnDelete();
            $table->foreignId('car_model_id')->nullable()->after('manufacturer_id')->constrained('car_models')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('failed_search_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manufacturer_id');
            $table->dropConstrainedForeignId('car_model_id');
        });
    }
};
