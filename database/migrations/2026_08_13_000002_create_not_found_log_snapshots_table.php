<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Idempotent (rule #42) via SafeSchema — see add_slug_to_products_table. */
    public function up(): void
    {
        if (Schema::hasTable('not_found_log_snapshots')) {
            return;
        }

        SafeSchema::create('create_not_found_log_snapshots_table', 'not_found_log_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('unresolved_count');
            $table->timestamp('recorded_at');
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_log_snapshots');
    }
};
