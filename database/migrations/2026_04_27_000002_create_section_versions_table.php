<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('action')->default('updated'); // created, updated, published, archived, restored
            $table->json('snapshot');   // full section data at this point
            $table->text('change_summary')->nullable();
            $table->timestamp('created_at');

            $table->index(['section_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_versions');
    }
};
