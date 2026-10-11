<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cleanup Engine — the run ledger behind System -> Cleanup.
 *
 * - cleanup_runs: one row per user action or scheduled batch. `updated_at` is the
 *   heartbeat (every advance() saves the row), `meta` holds the resume checkpoint.
 *   `active_key` is set to 'cleanup' while a run is non-terminal and NULL after it;
 *   its UNIQUE index makes "no two cleanups at once" an atomic database guarantee
 *   (NULLs never collide) that needs no cache driver, no lock file, no worker.
 * - cleanup_run_items: the per-target numbers of a run (a scheduled batch covers
 *   ~25 targets, history must show each one).
 * - cleanup_target_stats: cached row counts / sizes so the page never runs COUNT(*)
 *   or walks a directory while rendering (1M+ rows in production).
 * - cleanup_disk_samples: free/used space over time for the growth trend.
 *
 * Idempotent (existence-checked + SafeSchema) like every other migration here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cleanup_runs')) {
            SafeSchema::create('create_cleanup_runs', 'cleanup_runs', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 16)->default('single');          // single | batch
                $table->string('target', 64)->nullable();               // set when kind = single
                $table->string('mode', 16)->default('execute');         // dry_run | execute
                $table->string('trigger', 16)->default('manual');       // manual | scheduled | resume
                $table->string('status', 16)->default('pending');       // pending|running|paused|success|partial|failed|cancelled
                $table->string('step', 24)->nullable();                 // planning|deleting|verifying|reconciling|settling|finalizing
                $table->unsignedTinyInteger('percent')->default(0);
                $table->string('message', 255)->nullable();
                $table->unsignedBigInteger('targeted')->default(0);
                $table->unsignedBigInteger('deleted')->default(0);
                $table->unsignedBigInteger('failed')->default(0);
                $table->unsignedBigInteger('remaining')->default(0);
                $table->bigInteger('bytes_before')->nullable();
                $table->bigInteger('bytes_after')->nullable();
                $table->bigInteger('bytes_freed')->nullable();
                $table->timestamp('cutoff')->nullable();
                $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->unsignedBigInteger('resumed_from_id')->nullable();
                $table->unsignedBigInteger('preview_run_id')->nullable();
                $table->timestamp('cancel_requested_at')->nullable();
                $table->string('paused_reason', 48)->nullable();
                $table->text('error')->nullable();
                $table->json('reasons')->nullable();
                $table->json('meta')->nullable();                       // resume checkpoint (kept small)
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->string('active_key', 16)->nullable()->unique(); // 'cleanup' while non-terminal, else NULL
                $table->timestamps();

                $table->index('status');
                $table->index(['target', 'status']);
                $table->index('finished_at');
            });
        }

        if (! Schema::hasTable('cleanup_run_items')) {
            SafeSchema::create('create_cleanup_run_items', 'cleanup_run_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('run_id')->constrained('cleanup_runs')->cascadeOnDelete();
                $table->string('target', 64);
                $table->string('status', 24)->default('pending');       // + skipped_guard | skipped_floor | blocked_busy
                $table->timestamp('cutoff')->nullable();
                $table->unsignedInteger('retention_days')->nullable();
                $table->unsignedBigInteger('targeted')->default(0);
                $table->unsignedBigInteger('deleted')->default(0);
                $table->unsignedBigInteger('failed')->default(0);
                $table->unsignedBigInteger('remaining')->default(0);
                $table->unsignedBigInteger('external_gone')->default(0); // removed by someone else meanwhile
                $table->unsignedBigInteger('late_rows')->default(0);     // qualifying rows added after the run started
                $table->bigInteger('bytes_freed_apparent')->nullable();
                $table->bigInteger('bytes_freed_allocated')->nullable();
                $table->unsignedBigInteger('fs_free_before')->nullable();
                $table->unsignedBigInteger('fs_free_after')->nullable();
                $table->string('archive_path', 255)->nullable();
                $table->unsignedBigInteger('archive_rows')->nullable();
                $table->json('reasons')->nullable();
                $table->json('checkpoint')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['target', 'status']);
            });
        }

        if (! Schema::hasTable('cleanup_target_stats')) {
            SafeSchema::create('create_cleanup_target_stats', 'cleanup_target_stats', function (Blueprint $table) {
                $table->string('target', 64)->primary();
                $table->unsignedBigInteger('rows_est')->nullable();
                $table->unsignedBigInteger('bytes_est')->nullable();
                $table->timestamp('oldest_at')->nullable();
                $table->unsignedBigInteger('eligible_rows')->nullable();
                $table->unsignedBigInteger('eligible_bytes_est')->nullable();
                $table->timestamp('measured_at')->nullable();
            });
        }

        if (! Schema::hasTable('cleanup_disk_samples')) {
            SafeSchema::create('create_cleanup_disk_samples', 'cleanup_disk_samples', function (Blueprint $table) {
                $table->id();
                $table->timestamp('measured_at')->index();
                $table->unsignedBigInteger('total_bytes');
                $table->unsignedBigInteger('free_bytes');
                $table->unsignedBigInteger('used_bytes');
                $table->json('breakdown')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cleanup_run_items');
        Schema::dropIfExists('cleanup_runs');
        Schema::dropIfExists('cleanup_target_stats');
        Schema::dropIfExists('cleanup_disk_samples');
    }
};
