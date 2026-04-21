<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->string('name', 200);
            $table->enum('subject_type', [
                'general_inquiry', 'part_not_found', 'order_issue',
                'shipping_question', 'return_refund', 'b2b_partnership', 'other',
            ]);
            $table->string('order_number', 50)->nullable();
            $table->string('oem_number', 100)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->string('car_model', 100)->nullable();
            $table->string('year', 10)->nullable();
            $table->string('vin_number', 50)->nullable();
            $table->text('message');
            $table->enum('status', ['unread', 'read', 'resolved'])->default('unread');
            $table->boolean('otp_verified')->default(false);
            $table->string('ip_address', 45);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
