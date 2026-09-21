<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a Cart row as an isolated, throwaway "Buy Now" cart (own guest_token,
 * never written to the guest_token cookie, never merged with the customer's
 * real cart) so it's identifiable in DB triage even though the checkout flow
 * itself doesn't need to branch on this column — CheckoutService::createOrder()
 * deletes any cart it completes, buy-now or not, with no special-casing.
 *
 * Idempotent + reversible (rule #42) — via SafeSchema (see
 * add_slug_to_products_table for why a bare hasColumn() pre-check isn't
 * enough on its own for a table that real live upgrades pass through).
 */
return new class extends Migration
{
    public function up(): void
    {
        SafeSchema::table('add_is_buy_now_to_carts_table', 'carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'is_buy_now')) {
                $table->boolean('is_buy_now')->default(false)->after('coupon_code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('carts', 'is_buy_now')) {
            return;
        }

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('is_buy_now');
        });
    }
};
