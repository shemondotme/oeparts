<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ProductionRestoreService (self-service full files+DB downgrade — Update &
 * Recovery System) reuses the update_histories table as its audit trail
 * rather than inventing a parallel one, so a restore shows up on the
 * existing Update History page for free. 'type' distinguishes a restore row
 * from a normal update/auto-apply row; 'restore_of_backup_run_id' points at
 * the BackupRun it restored (no FK — same "keep history if the row is later
 * pruned" reasoning as the existing backup_run_id column on this table).
 *
 * Idempotent + reversible (rule #42), via SafeSchema — see
 * add_slug_to_products_table's doc comment for why a bare hasColumn()
 * pre-check alone isn't sufficient against a real production desync.
 */
return new class extends Migration
{
    public function up(): void
    {
        SafeSchema::table('add_type_to_update_histories_table:columns', 'update_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('update_histories', 'type')) {
                $table->string('type', 16)->default('update')->after('channel');
            }
            if (! Schema::hasColumn('update_histories', 'restore_of_backup_run_id')) {
                $table->unsignedBigInteger('restore_of_backup_run_id')->nullable()->after('backup_run_id');
            }
        });
        SafeSchema::table('add_type_to_update_histories_table:index', 'update_histories', function (Blueprint $table) {
            if (! Schema::hasIndex('update_histories', ['type'])) {
                $table->index('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('update_histories', function (Blueprint $table) {
            if (Schema::hasIndex('update_histories', ['type'])) {
                $table->dropIndex(['type']);
            }
            if (Schema::hasColumn('update_histories', 'restore_of_backup_run_id')) {
                $table->dropColumn('restore_of_backup_run_id');
            }
            if (Schema::hasColumn('update_histories', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
