<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Custom-invoice builder v2: a proper VAT treatment (the old reverse-charge on/off
 * switch could not express an export outside the EU or an intra-EU supply of goods),
 * a percentage discount, the date of supply, the client's purchase-order number,
 * delivery terms, printed terms & conditions, internal notes, and the per-rate VAT
 * breakdown. Line-level fields (part number, unit, line discount, line VAT rate)
 * live inside the existing items JSON and need no column.
 *
 * Additive, idempotent (each column guarded), reversible. Existing reverse-charge
 * invoices are carried over to the new treatment; the old column stays in step.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('custom_invoices', 'vat_treatment')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->string('vat_treatment', 20)->default('standard');
            });

            DB::table('custom_invoices')->where('reverse_charge', true)->update(['vat_treatment' => 'reverse_charge']);
        }

        if (! Schema::hasColumn('custom_invoices', 'vat_exemption_note')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->text('vat_exemption_note')->nullable();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'supply_date')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->date('supply_date')->nullable();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'discount_type')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->string('discount_type', 10)->default('amount');
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'discount_percent')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->decimal('discount_percent', 5, 2)->default(0);
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'po_number')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->string('po_number', 100)->nullable();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'delivery_terms')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->string('delivery_terms', 150)->nullable();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'terms_text')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->text('terms_text')->nullable();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'internal_notes')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->text('internal_notes')->nullable();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'vat_breakdown')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->json('vat_breakdown')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['vat_breakdown', 'internal_notes', 'terms_text', 'delivery_terms', 'po_number', 'discount_percent', 'discount_type', 'supply_date', 'vat_exemption_note', 'vat_treatment'] as $column) {
            if (Schema::hasColumn('custom_invoices', $column)) {
                Schema::table('custom_invoices', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
