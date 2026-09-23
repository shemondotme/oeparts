<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * orders.payment_method was never widened when Paysera was added as a second
 * gateway (unlike payments.gateway, see
 * 2026_08_09_000001_add_paysera_to_payments_gateway_enum.php) — the checkout
 * validation and App\Enums\PaymentMethod both accept 'paysera', but the DB
 * enum only allows 'card'/'bank_transfer', so paying via Paysera fails at
 * the order-update step with a data-truncated/constraint error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_method', ['card', 'paysera', 'bank_transfer'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_method', ['card', 'bank_transfer'])->change();
        });
    }
};
