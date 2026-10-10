<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The storefront language the customer ordered in. Order emails are sent
     * from queue jobs and gateway webhooks — no request, so no URL language —
     * and used to be hard-coded English for everyone.
     */
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'locale')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'locale')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
