<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backup Engine (Module 14/21) — one row per backup run (update-safety or full).
 * Append-only, idempotent, reversible — see CLAUDE.md rule #42.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backup_runs')) {
            return;
        }

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('profile', 24);                       // update_safety | full
            $table->string('status', 24)->default('pending');    // pending | running | success | failed
            $table->string('trigger', 24)->default('manual');    // manual | scheduled | pre_update
            $table->string('disk', 48)->default('local');        // local | s3 | sftp
            $table->boolean('encrypted')->default(true);         // AES-256-GCM (rule #45)
            $table->string('app_version', 32)->nullable();
            $table->string('php_version', 16)->nullable();
            $table->string('db_version', 32)->nullable();
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->unsignedInteger('part_count')->default(0);
            $table->string('manifest_path')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();         // GFS retention prune
            $table->text('error')->nullable();
            $table->json('meta')->nullable();                    // cursor/checkpoint for resume, kdf params
            $table->timestamps();

            $table->index(['profile', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
