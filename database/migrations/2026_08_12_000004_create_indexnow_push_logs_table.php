<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Idempotent (rule #42) via SafeSchema — see
     * add_slug_to_products_table for why a bare create-and-hope isn't
     * enough on its own for a table a live upgrade actually passes through.
     */
    public function up(): void
    {
        if (Schema::hasTable('indexnow_push_logs')) {
            return;
        }

        SafeSchema::create('create_indexnow_push_logs_table', 'indexnow_push_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('url_count');
            $table->string('status', 20); // success | failed
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indexnow_push_logs');
    }
};
