<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Stand-alone ("custom") invoices an admin writes by hand for a client who is
 * not buying through the storefront checkout. Numbers come from the SAME
 * invoice Sequence as order invoices so the numbering stays one gapless run;
 * for that reason rows are never deleted — a mistaken invoice is cancelled
 * (status = cancelled) and keeps its number.
 *
 * Line items live in a JSON column: they are only ever read/written together
 * with their invoice and totals are computed from them on save.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = [
        'view custom invoices',
        'create custom invoices',
        'edit custom invoices',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('custom_invoices')) {
            Schema::create('custom_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number', 50)->unique();
                $table->string('status', 20)->default('draft')->index();

                $table->string('client_name');
                $table->string('client_company')->nullable();
                $table->string('client_vat_number', 50)->nullable();
                $table->string('client_email')->nullable();
                $table->string('client_phone', 50)->nullable();
                $table->string('client_address_line1');
                $table->string('client_address_line2')->nullable();
                $table->string('client_city');
                $table->string('client_postal_code', 20)->nullable();
                $table->string('client_country_code', 2);

                $table->string('currency', 3)->default('EUR');
                $table->date('issue_date');
                $table->date('due_date');

                $table->json('items');
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->boolean('reverse_charge')->default(false);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('vat_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);

                $table->text('notes')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'admin'],
                ['name' => $name, 'guard_name' => 'admin'],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_invoices');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'admin')
            ->whereIn('name', $this->permissions)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
