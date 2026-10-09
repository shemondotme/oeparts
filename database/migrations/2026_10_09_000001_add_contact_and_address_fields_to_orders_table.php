<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders never stored the customer's phone number: the storefront checkout
 * collected it in step 1 and then dropped it when the order was created, so
 * nobody (courier, support, invoice) ever saw it. Also adds the address fields
 * a real order needs: a second street line, a state/region (Japan, the US, …),
 * and an optional separate billing address (B2B customers routinely invoice a
 * head office but ship to a workshop). All billing_* columns are nullable —
 * null means "same as shipping", exactly what every existing order is.
 *
 * Additive, idempotent (each column guarded), reversible. Single small table.
 * Columns are spelled out one by one (not looped) so static analysis can see them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'customer_phone')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('customer_phone', 50)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_address_line2')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_address_line2', 255)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_state')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_state', 100)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'billing_name')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('billing_name', 255)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'billing_address_line1')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('billing_address_line1', 255)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'billing_address_line2')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('billing_address_line2', 255)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'billing_city')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('billing_city', 100)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'billing_state')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('billing_state', 100)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'billing_postal_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('billing_postal_code', 20)->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'billing_country_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('billing_country_code', 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = [
            'customer_phone', 'shipping_address_line2', 'shipping_state',
            'billing_name', 'billing_address_line1', 'billing_address_line2', 'billing_city',
            'billing_state', 'billing_postal_code', 'billing_country_code',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
