<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Update System (Module 21) — records every in-app update attempt.
 * Append-only, idempotent (guarded), reversible — see CLAUDE.md rule #42.
 * This table's schema must stay stable (the updater/recovery depend on it).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('update_histories')) {
            return;
        }

        Schema::create('update_histories', function (Blueprint $table) {
            $table->id();
            $table->string('from_version', 32)->nullable();
            $table->string('to_version', 32);
            $table->string('channel', 16)->default('stable');
            // pending | preflight | backing_up | downloading | extracting | swapping
            // | migrating | finalizing | success | failed | rolled_back
            $table->string('status', 24)->default('pending');
            $table->string('step', 48)->nullable();          // current FSM step (for resume)
            $table->unsignedBigInteger('initiated_by')->nullable(); // admins.id — no FK: keep history if admin deleted
            $table->unsignedBigInteger('backup_run_id')->nullable(); // pre-update safety backup
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();                // sha256, migration_count, sizes, notes
            $table->timestamps();

            $table->index('status');
            $table->index('to_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_histories');
    }
};
