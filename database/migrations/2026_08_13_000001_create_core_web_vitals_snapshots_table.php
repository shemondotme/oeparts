<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_web_vitals_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lcp_ms')->nullable();
            $table->decimal('cls', 5, 3)->nullable();
            $table->unsignedInteger('inp_ms')->nullable();
            $table->string('lcp_rating')->nullable();
            $table->string('cls_rating')->nullable();
            $table->string('inp_rating')->nullable();
            $table->timestamp('recorded_at');
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_web_vitals_snapshots');
    }
};
