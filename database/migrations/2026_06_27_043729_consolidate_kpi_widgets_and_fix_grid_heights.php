<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `revenue_kpi`, `new_orders_kpi`, and `pending_orders_kpi` were consolidated
 * into one `order_stats_overview` widget; `latest_customers` and
 * `cache_status` had their default grid height raised to match the widgets
 * they sit beside. Removed widget ids are silently dropped from existing
 * saved layouts by DashboardLayoutService::canvasItems() rather than
 * auto-migrated, so any admin who already had a dashboard saved before this
 * change would simply lose those 3 KPI cards with no replacement. Truncating
 * forces every admin's dashboards to re-seed cleanly from the corrected
 * registry on next load — same pattern as the prior
 * 2026_06_23_000001_reset_admin_dashboards_for_tab_rebalance migration.
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
