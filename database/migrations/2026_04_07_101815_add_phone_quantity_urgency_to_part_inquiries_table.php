<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('part_inquiries', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->after('email');
            $table->unsignedTinyInteger('quantity')->default(1)->after('vin_number');
            $table->enum('urgency', ['normal', 'soon', 'urgent'])->default('normal')->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('part_inquiries', function (Blueprint $table) {
            $table->dropColumn(['phone', 'quantity', 'urgency']);
        });
    }
};
