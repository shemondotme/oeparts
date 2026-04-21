<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name', 100);
            $table->enum('status', ['success', 'failed']);
            $table->integer('duration_ms');
            $table->text('output')->nullable();
            $table->timestamp('ran_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_logs');
    }
};
