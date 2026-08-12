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
        Schema::create('indexnow_push_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('url_count');
            $table->string('status', 20); // success | failed
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indexnow_push_logs');
    }
};
