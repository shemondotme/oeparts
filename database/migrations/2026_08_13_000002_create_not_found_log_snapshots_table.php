<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('not_found_log_snapshots', function (Blueprint $table) {
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
