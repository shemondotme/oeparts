<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two SEO settings the admin form needs a row for: "Languages in Search" (empty = every language, i.e. no
 * behaviour change) and the live sitemap update for the small blog / pages files (on).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach ([
            ['key' => 'indexed_locales', 'value' => '[]', 'type' => 'json'],
            ['key' => 'sitemap_live_update', 'value' => '1', 'type' => 'boolean'],
        ] as $row) {
            if (! DB::table('settings')->where('group', 'seo')->where('key', $row['key'])->exists()) {
                DB::table('settings')->insert($row + ['group' => 'seo', 'is_encrypted' => false]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'seo')->whereIn('key', ['indexed_locales', 'sitemap_live_update'])->delete();
    }
};
