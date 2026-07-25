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
        Schema::create('health_check_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('check_key', 50);
            $table->string('status', 20);
            $table->string('detail', 255)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamp('checked_at');

            // Serves both "latest row for a check" (transition lookup in
            // HealthCheckService::snapshot()) and "last N rows for a check"
            // (sparkline read) — both filter by check_key and order by
            // checked_at.
            $table->index(['check_key', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_check_snapshots');
    }
};
