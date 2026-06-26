<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Consolidates the 12 trial-and-error "reset_admin_dashboards_layout_v1..v11"
 * + "reset_command_center_layout" migrations into one. Those existed because
 * WidgetPreferenceService::WIDGET_TABS and DashboardLayoutService::
 * TAB_BLUEPRINT_LAYOUTS had drifted apart (parts_inquiry and latest_customers
 * each rendered in two tabs for newly-seeded admins). Both are now corrected
 * and kept in sync, and all 4 tabs (not just Command Center) have a
 * pixel-perfect blueprint. Truncating forces every admin's dashboards to
 * re-seed cleanly from the corrected registry on next load.
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
