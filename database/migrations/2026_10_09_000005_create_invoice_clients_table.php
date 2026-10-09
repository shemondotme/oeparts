<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every custom invoice meant retyping the client's name, company, VAT number and
 * address. Saved clients are picked on the invoice form (and a new client can be saved
 * from it); a client can optionally be tied to a registered customer account.
 * custom_invoices gets client_id (which saved client it came from) and client_state
 * (region/prefecture: needed for addresses in Japan, the US, ...).
 *
 * Additive, idempotent, reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_clients')) {
            Schema::create('invoice_clients', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('company', 255)->nullable()->index();
                $table->string('vat_number', 50)->nullable();
                $table->string('email', 255)->nullable()->index();
                $table->string('phone', 50)->nullable();
                $table->string('address_line1', 255);
                $table->string('address_line2', 255)->nullable();
                $table->string('city', 255);
                $table->string('state', 100)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('country_code', 2);
                $table->string('currency', 3)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'client_id')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->foreignId('client_id')->nullable()->constrained('invoice_clients')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'client_state')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->string('client_state', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('custom_invoices', 'client_id')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('client_id');
            });
        }

        if (Schema::hasColumn('custom_invoices', 'client_state')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->dropColumn('client_state');
            });
        }

        Schema::dropIfExists('invoice_clients');
    }
};
