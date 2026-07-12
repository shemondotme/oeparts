<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Configurability audit carry-forward (§5v): seo.sitemap_search_log_days
 * promised "custom keyword routes from search logs" in the generated
 * sitemap, but SitemapService has never had any concept of a keyword
 * landing page — no route, no controller, nothing reads SearchLog for
 * sitemap purposes. Building it for real would mean indexing raw
 * search-query pages, which cuts against robots.txt's existing deliberate
 * strategy of disallowing faceted-filter/search-query params so only
 * canonical /parts/{oem} pages get indexed. Retired outright (no live
 * replacement to carry a value into). Idempotent + reversible (rule #42);
 * single-group op (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        $deleted = DB::table('settings')
            ->where('group', 'seo')
            ->where('key', 'sitemap_search_log_days')
            ->delete();

        if ($deleted > 0) {
            Cache::forget('settings.seo');
        }
    }

    public function down(): void
    {
        // Nothing safe to restore — this knob never drove real behavior.
    }
};
