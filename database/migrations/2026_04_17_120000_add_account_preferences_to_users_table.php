<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('prefers_order_notifications')->default(true)->after('is_active');
            $table->boolean('prefers_email_notifications')->default(true)->after('prefers_order_notifications');
            $table->boolean('prefers_promotional_emails')->default(false)->after('prefers_email_notifications');
            $table->string('preferred_locale', 5)->nullable()->after('prefers_promotional_emails');
            $table->string('timezone', 50)->nullable()->after('preferred_locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'prefers_order_notifications',
                'prefers_email_notifications',
                'prefers_promotional_emails',
                'preferred_locale',
                'timezone',
            ]);
        });
    }
};
