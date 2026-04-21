<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('gateway', ['airwallex', 'bank_transfer']);
            $table->string('transaction_id', 200)->nullable();
            $table->enum('status', ['pending', 'authorized', 'captured', 'failed', 'refunded']);
            $table->decimal('amount', 10, 2);
            $table->json('gateway_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
