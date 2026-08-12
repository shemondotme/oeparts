<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SearchService::search() already knows which of exact/cross_reference/
 * partial matched (it's the literal string passed to buildResult()'s
 * $matchType param), but SearchService::logSearch() never persisted it —
 * every search_logs row recorded a hit with no way to tell which match
 * path served it. The SEO Health Dashboard's internal-search-analytics
 * widget needs this breakdown and has no other source for it.
 *
 * Idempotent + reversible (rule #42).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('search_logs', 'match_type')) {
                $table->string('match_type', 20)->nullable()->after('result_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('search_logs', function (Blueprint $table) {
            if (Schema::hasColumn('search_logs', 'match_type')) {
                $table->dropColumn('match_type');
            }
        });
    }
};
