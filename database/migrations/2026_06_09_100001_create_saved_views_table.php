<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('resource', 100);
            $table->json('filters')->nullable();
            $table->string('sort_column', 100)->nullable();
            $table->string('sort_direction', 10)->nullable();
            $table->string('search', 255)->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
