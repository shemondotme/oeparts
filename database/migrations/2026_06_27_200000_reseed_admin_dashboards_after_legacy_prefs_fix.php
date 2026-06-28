<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WidgetPreferenceService::getAdminPreferences() only stripped `_meta` from
 * `admins.dashboard_preferences` before this fix — but AdminNavService stores
 * unrelated sidebar housekeeping (`pinned_nav`, `recent_nav`) on that same
 * column. Any admin who had ever navigated the sidebar before their first-
 * ever dashboard auto-seed got those leftover keys mistaken for real widget
 * preferences by DashboardLayoutService::legacySeedWidgetIds(), which fell
 * back to a naive auto-pack (slug `my-dashboard`) instead of the curated
 * TAB_BLUEPRINT_LAYOUTS blueprint (slug `command-center` etc.) — producing
 * wrong widget widths/pairing. That root cause is now fixed in
 * WidgetPreferenceService; this truncates admin_dashboards once more (same
 * pattern as the two prior reset migrations) so every admin re-seeds
 * correctly from the blueprint on next load.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_dashboards')->truncate();
    }

    public function down(): void
    {
        // No rollback — layout preferences are user-customisable; down() is a no-op.
    }
};
