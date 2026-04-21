<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('failed_search_log_id')->nullable()->constrained('failed_search_logs')->nullOnDelete();
            $table->string('email', 255);
            $table->string('oem_number', 100);
            $table->string('manufacturer', 100)->nullable();
            $table->string('car_model', 100)->nullable();
            $table->string('year', 10)->nullable();
            $table->string('vin_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['new', 'reviewing', 'sourced', 'unavailable'])->default('new');
            $table->text('admin_note')->nullable();
            $table->string('ip_address', 45);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_inquiries');
    }
};
