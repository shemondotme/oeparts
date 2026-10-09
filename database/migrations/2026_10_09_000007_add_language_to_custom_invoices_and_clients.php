<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The PDF and the email of a hand-written document were English only. Each document
 * now carries the language it is written in (en, de, es, fr, lt), and a saved client
 * remembers theirs so it is picked up automatically.
 *
 * Additive, idempotent, reversible. Existing documents stay English.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('custom_invoices', 'language')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->string('language', 5)->default('en');
            });
        }

        if (! Schema::hasColumn('invoice_clients', 'language')) {
            Schema::table('invoice_clients', function (Blueprint $table) {
                $table->string('language', 5)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_clients', 'language')) {
            Schema::table('invoice_clients', function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }

        if (Schema::hasColumn('custom_invoices', 'language')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }
    }
};
