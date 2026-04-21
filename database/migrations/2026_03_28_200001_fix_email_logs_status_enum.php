<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE email_logs MODIFY COLUMN status ENUM('success','failed') NOT NULL");
        } else {
            // SQLite does not support ALTER COLUMN; drop and recreate with corrected constraint.
            Schema::drop('email_logs');
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
                $table->enum('status', ['success', 'failed']);
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE email_logs MODIFY COLUMN status ENUM('sent','failed') NOT NULL");
        }
        // SQLite: no reverse needed (table is empty at migration time)
    }
};
