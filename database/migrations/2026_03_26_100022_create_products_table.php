<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained('manufacturers')->cascadeOnDelete();
            $table->string('oem_number', 100);
            $table->string('normalized_oem', 100)->index();
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->enum('condition', [
                'new', 'used', 'used_grade_a', 'used_grade_b',
                'used_grade_c', 'remanufactured', 'aftermarket', 'new_old_stock',
            ]);
            $table->decimal('price', 10, 2);
            $table->string('delivery_time', 50)->nullable();
            $table->integer('moq')->default(1);
            $table->boolean('is_in_stock')->default(true)->index();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
