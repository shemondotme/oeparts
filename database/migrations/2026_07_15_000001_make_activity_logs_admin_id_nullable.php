<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * activity_logs.admin_id was NOT NULL, but customer-initiated order/refund
 * actions (e.g. AccountController::requestRefund() -> OrderService::
 * transitionStatus() -> OrderStatusChanged -> LogOrderStatusChange listener)
 * legitimately have no admin_id — auth('admin')->id() is null in that
 * request. The listener's own try/catch already prevents this from
 * crashing the request, but it silently drops the activity-log entry for
 * every customer-initiated status change, confirmed live (real refund
 * submission -> "Integrity constraint violation: 1048 Column 'admin_id'
 * cannot be null" logged and swallowed). Making the column nullable lets
 * these entries actually get recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('activity_logs', 'admin_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->foreignId('admin_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_logs', 'admin_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->foreignId('admin_id')->nullable(false)->change();
            });
        }
    }
};
