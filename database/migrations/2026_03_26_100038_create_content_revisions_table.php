<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('revisionable_type', 100);
            $table->unsignedBigInteger('revisionable_id');
            $table->json('content_snapshot');
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['revisionable_type', 'revisionable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
    }
};
