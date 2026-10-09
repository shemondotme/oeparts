<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices could only be "paid" or not: no partial payments, no record of when,
 * how or against which reference money arrived, and nothing chased a client who
 * had not paid.
 *
 *  - custom_invoice_payments: every payment received (amount, date, method, reference)
 *  - custom_invoices.reminder_count / last_reminded_at: how often a client was chased
 *  - two settings rows so the reminder switch and schedule can be edited in the admin
 *    (the settings page saves into existing rows, and a seeder does not re-run on update)
 *
 * Additive, idempotent, reversible. Reminders are OFF by default: emailing clients
 * on its own is something the admin must choose.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_invoice_payments')) {
            Schema::create('custom_invoice_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('custom_invoice_id')->constrained('custom_invoices')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->date('paid_on');
                $table->string('method', 30)->nullable();
                $table->string('reference', 150)->nullable();
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'reminder_count')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->unsignedSmallInteger('reminder_count')->default(0);
            });
        }

        if (! Schema::hasColumn('custom_invoices', 'last_reminded_at')) {
            Schema::table('custom_invoices', function (Blueprint $table) {
                $table->timestamp('last_reminded_at')->nullable();
            });
        }

        foreach ([
            ['key' => 'reminders_enabled', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'reminder_days', 'value' => '3,10,21', 'type' => 'string'],
        ] as $row) {
            $exists = DB::table('settings')->where('group', 'invoice')->where('key', $row['key'])->exists();
            if (! $exists) {
                DB::table('settings')->insert($row + ['group' => 'invoice', 'is_encrypted' => false, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'invoice')->whereIn('key', ['reminders_enabled', 'reminder_days'])->delete();

        foreach (['last_reminded_at', 'reminder_count'] as $column) {
            if (Schema::hasColumn('custom_invoices', $column)) {
                Schema::table('custom_invoices', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        Schema::dropIfExists('custom_invoice_payments');
    }
};
