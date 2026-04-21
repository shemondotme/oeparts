<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('featured_image_id')->nullable()->after('content')->constrained('media_files')->nullOnDelete();
            $table->json('meta_title')->nullable()->after('featured_image_id');
            $table->json('meta_description')->nullable()->after('meta_title');
            $table->boolean('is_homepage')->default(false)->after('meta_description');
            $table->boolean('is_header')->default(false)->after('is_homepage');
            $table->boolean('is_footer')->default(false)->after('is_header');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['featured_image_id']);
            $table->dropColumn(['featured_image_id', 'meta_title', 'meta_description', 'is_homepage', 'is_header', 'is_footer']);
        });
    }
};
