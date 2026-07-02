<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backup Engine — individual chunked parts of a backup run (db table chunks,
 * file volumes, env). Enables resumable + partial restore.
 * Append-only, idempotent, reversible — see CLAUDE.md rule #42.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backup_parts')) {
            return;
        }

        Schema::create('backup_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_run_id')->constrained('backup_runs')->cascadeOnDelete();
            $table->string('type', 16);                     // db | files | env | other
            $table->unsignedInteger('sequence')->default(0);
            $table->string('name')->nullable();             // db table name, or volume label
            $table->string('disk', 48)->default('local');
            $table->string('path');
            $table->string('sha256', 128)->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedBigInteger('rows')->nullable(); // db parts only
            $table->json('meta')->nullable();               // encryption iv, keyset cursor, etc.
            $table->timestamps();

            $table->unique(['backup_run_id', 'type', 'sequence']);
            $table->index(['backup_run_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_parts');
    }
};
