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
        Schema::create('not_found_logs', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500);
            $table->string('lang', 5)->nullable();
            $table->string('referer', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('hit_count')->default(1);
            $table->boolean('resolved')->default(false);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            // path is unbounded length in practice (500 cap), so index a hash
            // of it rather than the raw column (avoids the MySQL 767/3072-byte
            // index-key-length limit on utf8mb4 long strings).
            $table->string('path_hash', 64)->unique();
            $table->index('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
    }
};
