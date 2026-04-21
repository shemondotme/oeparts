<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('search_query', 200)->index();
            $table->string('normalized_query', 200);
            $table->string('lang', 5);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45);
            $table->boolean('inquiry_submitted')->default(false);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_search_logs');
    }
};
