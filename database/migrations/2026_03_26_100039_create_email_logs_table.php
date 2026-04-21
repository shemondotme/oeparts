<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('to_email', 255);
            $table->string('subject', 255);
            $table->enum('template_type', [
                'order_confirmation', 'order_status', 'order_shipped',
                'welcome', 'otp', 'refund_processed', 'abandoned_cart',
                'newsletter_confirm', 'password_reset', 'contact_reply',
            ]);
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('related_type', 100)->nullable();
            $table->enum('status', ['sent', 'failed']);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
