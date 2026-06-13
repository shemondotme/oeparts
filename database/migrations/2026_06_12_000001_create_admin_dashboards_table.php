<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->json('layout')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['admin_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_dashboards');
    }
};
