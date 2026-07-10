<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * template_type was a MySQL ENUM — every new mailable type required DDL (this
 * is already the column's second re-creation), and unknown values are a hard
 * insert error. A plain string accepts all existing values (Expand-safe,
 * rule #43); the value set is governed by the EmailTemplate PHP enum cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('email_logs', 'template_type')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            // sqlite enums are TEXT + a CHECK constraint that would reject new
            // values — recreate without it (same pattern as the earlier
            // fix_email_logs_status_enum migration; test DBs are ephemeral).
            Schema::drop('email_logs');
            Schema::create('email_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('to_email', 255);
                $table->string('subject', 255);
                $table->string('template_type', 50);
                $table->unsignedBigInteger('related_id')->nullable();
                $table->string('related_type', 100)->nullable();
                $table->enum('status', ['success', 'failed']);
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at');
                $table->timestamp('created_at')->nullable();
            });

            return;
        }

        // ->change() on an ENUM column is unsupported by the schema builder —
        // raw DDL for MySQL/MariaDB.
        DB::statement('ALTER TABLE email_logs MODIFY template_type VARCHAR(50) NOT NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasColumn('email_logs', 'template_type')) {
            DB::statement("ALTER TABLE email_logs MODIFY template_type ENUM(
                'order_confirmation', 'order_status', 'order_shipped',
                'welcome', 'otp', 'refund_processed', 'abandoned_cart',
                'newsletter_confirm', 'password_reset', 'contact_reply'
            ) NOT NULL");
        }
    }
};
