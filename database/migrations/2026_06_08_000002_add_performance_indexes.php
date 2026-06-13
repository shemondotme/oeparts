<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('price');
            $table->index(['manufacturer_id', 'is_active']);
            $table->index('oem_number');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('payment_status');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['model_type', 'model_id']);
            $table->index('action');
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->index('status');
            $table->index('template_type');
            $table->index('sent_at');
        });

        Schema::table('login_logs', function (Blueprint $table) {
            $table->index('status');
            $table->index('email');
            $table->index('ip_address');
        });

        Schema::table('search_logs', function (Blueprint $table) {
            $table->index('result_count');
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('otps', function (Blueprint $table) {
            $table->index('purpose');
            $table->index('expires_at');
        });

        Schema::table('part_inquiries', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->index('is_active');
        });

        Schema::table('abandoned_carts', function (Blueprint $table) {
            $table->index('recovery_email_sent');
        });

        Schema::table('redirects', function (Blueprint $table) {
            $table->index('is_active');
        });

        Schema::table('ip_blocklists', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('expires_at');
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['price']);
            $table->dropIndex(['manufacturer_id', 'is_active']);
            $table->dropIndex(['oem_number']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['payment_status']);
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['model_type', 'model_id']);
            $table->dropIndex(['action']);
        });
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['template_type']);
            $table->dropIndex(['sent_at']);
        });
        Schema::table('login_logs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['email']);
            $table->dropIndex(['ip_address']);
        });
        Schema::table('search_logs', function (Blueprint $table) {
            $table->dropIndex(['result_count']);
        });
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });
        Schema::table('otps', function (Blueprint $table) {
            $table->dropIndex(['purpose']);
            $table->dropIndex(['expires_at']);
        });
        Schema::table('part_inquiries', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
        Schema::table('abandoned_carts', function (Blueprint $table) {
            $table->dropIndex(['recovery_email_sent']);
        });
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
        Schema::table('ip_blocklists', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['expires_at']);
        });
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
