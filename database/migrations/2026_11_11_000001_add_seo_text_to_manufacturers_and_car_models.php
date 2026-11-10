<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand and car-model pages are the site's main landing pages ("BMW 3 Series
 * parts"), yet their title, description and body text came only from a
 * generic template — there was nowhere to write a page's own SEO text. Three
 * translatable (per-locale JSON) fields per table, all optional: when empty
 * the existing template output is used exactly as before.
 *
 * Both tables are small (brands / models, not products), so a plain ALTER is
 * fine. Idempotent + reversible.
 */
return new class extends Migration
{
    private const TABLES = ['manufacturers', 'car_models'];

    private const COLUMNS = ['meta_title', 'meta_description', 'intro'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->json($column)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($column) {
                        $blueprint->dropColumn($column);
                    });
                }
            }
        }
    }
};
