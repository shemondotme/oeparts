<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            // Add status column (default: published for backward compatibility)
            $table->string('status')->default('published')->after('is_active');
            
            // Add publish_at for scheduled sections
            $table->timestamp('publish_at')->nullable()->after('status');
            
            // Add published_by tracking
            $table->unsignedBigInteger('published_by')->nullable()->after('publish_at');
            
            // Add updated_by tracking for audit
            $table->unsignedBigInteger('updated_by')->nullable()->after('published_by');
            
            // Drop is_active (replaced by status)
            // We'll do this in a separate down() if needed
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['status', 'publish_at', 'published_by', 'updated_by']);
        });
    }
};
