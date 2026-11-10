<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per day of Google Search Console totals (clicks, impressions, CTR,
 * average position). The dashboard only ever showed a live 28-day window, so
 * there was no way to see whether traffic rose or fell after a change — or
 * to look back at all once a day aged out of Google's own retention.
 * Idempotent via SafeSchema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gsc_daily_stats')) {
            return;
        }

        SafeSchema::create('create_gsc_daily_stats_table', 'gsc_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->unique();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 7, 4)->default(0);
            $table->decimal('position', 7, 2)->default(0);
            $table->timestamp('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_daily_stats');
    }
};
