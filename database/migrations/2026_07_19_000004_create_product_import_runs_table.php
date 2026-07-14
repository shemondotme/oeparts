<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk Product Import redesign — one row per import run.
 * Drives the chunked, resumable Import Engine FSM (mirrors backup_runs).
 * Append-only, idempotent, reversible — see CLAUDE.md rule #42.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_import_runs')) {
            return;
        }

        Schema::create('product_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('status', 24)->default('running'); // running | success | failed
            $table->string('original_filename');
            $table->string('disk', 48)->default('local');
            $table->string('path');
            $table->boolean('update_existing')->default(false);
            $table->unsignedBigInteger('total_rows')->nullable();
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();  // capped list of {row, message}
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();   // fatal/file-level error
            $table->json('meta')->nullable();    // checkpoint + headers + data_start_offset
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_runs');
    }
};
