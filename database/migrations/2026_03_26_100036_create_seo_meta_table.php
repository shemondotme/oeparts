<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('metable_type', 100);
            $table->unsignedBigInteger('metable_id');
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->foreignId('og_image_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('robots', 100)->default('index,follow');
            $table->timestamps();

            $table->index(['metable_type', 'metable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
