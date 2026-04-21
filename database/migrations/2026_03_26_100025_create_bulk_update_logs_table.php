<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_update_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->enum('action_type', ['price_increase', 'price_decrease', 'stock_in', 'stock_out', 'import']);
            $table->foreignId('target_manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->integer('affected_rows_count');
            $table->json('payload');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_update_logs');
    }
};
