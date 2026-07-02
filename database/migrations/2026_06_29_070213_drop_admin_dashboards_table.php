<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admin_dashboards');
    }

    public function down(): void
    {
        // Recreating the table would require the original migration; append-only policy.
    }
};
