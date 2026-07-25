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
        Schema::create('cache_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('hit_rate'); // 0-100
            $table->unsignedBigInteger('memory_used_bytes');
            $table->unsignedBigInteger('memory_max_bytes')->nullable();
            $table->decimal('fragmentation_ratio', 5, 2)->nullable();
            $table->unsignedInteger('evicted_keys')->default(0);
            $table->unsignedInteger('ops_per_sec')->nullable();
            $table->unsignedInteger('total_keys')->default(0);
            $table->timestamp('recorded_at');
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache_metric_snapshots');
    }
};
