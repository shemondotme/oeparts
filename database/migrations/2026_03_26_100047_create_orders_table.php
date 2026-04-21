<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_email', 255)->nullable();

            $table->enum('status', [
                'pending', 'paid', 'processing', 'shipped',
                'delivered', 'cancelled', 'refund_requested', 'refunded',
            ]);
            $table->enum('payment_method', ['card', 'bank_transfer']);
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded']);
            $table->string('payment_reference', 100)->nullable();

            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default('0.00');
            $table->decimal('shipping_cost', 10, 2);
            $table->decimal('vat_amount', 10, 2);
            $table->decimal('grand_total', 10, 2);

            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();
            $table->string('shipping_method_name_snapshot', 200)->nullable();
            $table->integer('shipping_estimated_days_min')->nullable();
            $table->integer('shipping_estimated_days_max')->nullable();

            // Address snapshot
            $table->string('shipping_name', 200);
            $table->string('shipping_address_line1', 255);
            $table->string('shipping_city', 100);
            $table->string('shipping_postal_code', 20);
            $table->string('shipping_country_code', 2);

            // B2B
            $table->boolean('is_b2b')->default(false);
            $table->string('company_name', 200)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->boolean('vat_exempt')->default(false);

            // UTM
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('utm_content', 100)->nullable();

            // Other
            $table->text('customer_note')->nullable();
            $table->string('ip_address', 45);
            $table->string('tracking_number', 100)->nullable();
            $table->string('carrier', 100)->nullable();
            $table->boolean('urgent_processing')->default(false);
            $table->decimal('urgent_processing_fee', 10, 2)->default('0.00');
            $table->string('invoice_number', 30)->nullable()->unique();

            $table->softDeletes();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
