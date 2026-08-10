<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guest's email is only ever known inside the ephemeral checkout session
 * (CheckoutService's session-backed store, expiring per checkout.timeout_
 * minutes) — it was never persisted onto the Cart itself. ProcessAbandoned
 * Carts therefore had no reachable address for any guest cart and silently
 * skipped the entire guest-checkout segment of abandoned-cart recovery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('guest_email', 255)->nullable()->after('guest_token');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('guest_email');
        });
    }
};
