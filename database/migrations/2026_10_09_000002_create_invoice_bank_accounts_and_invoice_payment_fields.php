<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Custom invoices could only ever print the ONE bank account saved in Settings,
 * with no way to see or choose it per invoice. This adds:
 *
 *  - invoice_bank_accounts: any number of extra accounts (e.g. a local account for
 *    each currency, an international one with SWIFT and intermediary bank);
 *  - on custom_invoices: how the client should pay (bank transfer / online link /
 *    cash / other / nothing), which account to show (null = automatic), free-text
 *    payment instructions, and an online payment link;
 *  - the 'manage bank accounts' permission — changing where money is sent is the
 *    classic invoice-fraud move, so it is not folded into 'edit custom invoices'.
 *
 * Additive, idempotent (guarded), reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_bank_accounts')) {
            Schema::create('invoice_bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('label', 100);
                $table->string('currency', 3)->nullable()->index();
                $table->string('account_holder', 150);
                $table->string('bank_name', 150)->nullable();
                $table->string('iban', 40);
                $table->string('bic', 20)->nullable();
                $table->text('intermediary_bank')->nullable();
                $table->text('instructions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'payment_method')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->string('payment_method', 20)->default('bank_transfer');
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'bank_account_id')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->foreignId('bank_account_id')->nullable()->constrained('invoice_bank_accounts')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'payment_instructions')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->text('payment_instructions')->nullable();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'payment_link_url')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->string('payment_link_url', 500)->nullable();
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::updateOrCreate(
            ['name' => 'manage bank accounts', 'guard_name' => 'admin'],
            ['name' => 'manage bank accounts', 'guard_name' => 'admin'],
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasColumn('custom_invoices', 'bank_account_id')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('bank_account_id');
            });
        }

        foreach (['payment_link_url', 'payment_instructions', 'payment_method'] as $column) {
            if (Schema::hasColumn('custom_invoices', $column)) {
                Schema::table('custom_invoices', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        Schema::dropIfExists('invoice_bank_accounts');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->where('guard_name', 'admin')->where('name', 'manage bank accounts')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
