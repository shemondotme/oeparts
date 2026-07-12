<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SettingsSeeder previously seeded social_links.footer_icon_style with the
 * value 'outline', but SocialLinkSettings' Select field only defines options
 * 'filled'/'outlined' — 'outline' matches neither, so every install seeded
 * from the old seeder shows this field as an unmatched/blank selection in
 * the admin, despite a real row existing. Normalizes any existing 'outline'
 * value to the correct 'outlined' option key. Idempotent (only touches rows
 * still holding the legacy typo) + reversible-with-no-op-down (rule #42);
 * single-row op (rule #44).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'social_links')
            ->where('key', 'footer_icon_style')
            ->where('value', 'outline')
            ->update(['value' => 'outlined']);

        Cache::forget('settings.social_links');
    }

    public function down(): void
    {
        // Nothing safe to restore — cannot distinguish an operator-chosen
        // 'outlined' from the auto-corrected legacy 'outline' typo.
    }
};
