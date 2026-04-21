<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bulk_update_logs', function (Blueprint $table) {
            // Add new columns for generic bulk update
            $table->string('entity_type', 50)->after('admin_id')->nullable()->comment('products, manufacturers, car_models');
            $table->json('filters')->after('entity_type')->nullable();
            $table->json('updates')->after('filters')->nullable();
            $table->string('ip_address', 45)->after('affected_rows_count')->nullable();
            $table->text('user_agent')->after('ip_address')->nullable();
            
            // Make existing columns nullable to support both old and new formats
            $table->string('action_type')->nullable()->change();
            $table->unsignedBigInteger('target_manufacturer_id')->nullable()->change();
            $table->json('payload')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulk_update_logs', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn(['entity_type', 'filters', 'updates', 'ip_address', 'user_agent']);
            
            // Restore original column nullability (assuming they were NOT NULL originally)
            // Note: This might not be perfectly reversible without knowing original state
            $table->string('action_type')->nullable(false)->change();
            $table->unsignedBigInteger('target_manufacturer_id')->nullable(false)->change();
            $table->json('payload')->nullable(false)->change();
        });
    }
};
