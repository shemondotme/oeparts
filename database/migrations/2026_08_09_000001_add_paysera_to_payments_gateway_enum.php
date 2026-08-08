<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN gateway ENUM('airwallex','paysera','bank_transfer') NOT NULL");

            return;
        }

        // SQLite has no ALTER COLUMN — Laravel emulates enum() there via a CHECK
        // constraint, so widening it means recreating the table (same approach
        // as 2026_03_28_200001_fix_email_logs_status_enum.php). Existing rows
        // are preserved via a copy-through.
        Schema::create('payments_tmp_paysera_enum', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('gateway', ['airwallex', 'paysera', 'bank_transfer']);
            $table->string('transaction_id', 200)->nullable();
            $table->enum('status', ['pending', 'authorized', 'captured', 'failed', 'refunded']);
            $table->decimal('amount', 10, 2);
            $table->json('gateway_response')->nullable();
            $table->timestamps();
        });

        DB::statement('INSERT INTO payments_tmp_paysera_enum SELECT * FROM payments');
        Schema::drop('payments');
        Schema::rename('payments_tmp_paysera_enum', 'payments');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN gateway ENUM('airwallex','bank_transfer') NOT NULL");
        }
        // SQLite: no reverse needed (widening is backward-compatible; a 'paysera'
        // row present at rollback time would be a genuine data conflict either way).
    }
};
