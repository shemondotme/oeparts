<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The invoice builder only knew one document: an invoice. A parts business quotes
 * first, often asks for a prepayment (proforma), invoices, and needs a credit note
 * to correct a sent invoice (a numbered invoice can only be cancelled today).
 *
 *  - custom_invoices.document_type: quote | proforma | invoice | credit_note
 *  - custom_invoices.parent_id:     the document this one was created from
 *                                   (quote -> proforma -> invoice, invoice -> credit note)
 *  - sequences.type: widened so each type draws from its own number series
 *
 * Additive, idempotent, reversible. Existing rows are invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('custom_invoices', 'document_type')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->string('document_type', 20)->default('invoice')->index();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'parent_id')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->constrained('custom_invoices')->nullOnDelete();
            });
        }

        Schema::table('sequences', function (Blueprint $table) {
            $table->enum('type', ['order', 'invoice', 'rma', 'quote', 'proforma', 'credit_note'])->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('custom_invoices', 'parent_id')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('parent_id');
            });
        }

        if (Schema::hasColumn('custom_invoices', 'document_type')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->dropColumn('document_type');
            });
        }

        // The widened enum is left in place: narrowing it would fail as soon as any
        // quote/proforma/credit-note number series exists.
    }
};
