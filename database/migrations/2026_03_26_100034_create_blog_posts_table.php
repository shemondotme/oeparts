<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->json('title');
            $table->string('slug', 200)->unique();
            $table->json('excerpt')->nullable();
            $table->json('content');
            $table->foreignId('featured_image_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->foreignId('author_id')->constrained('admins')->cascadeOnDelete();
            $table->enum('status', ['draft', 'published']);
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
