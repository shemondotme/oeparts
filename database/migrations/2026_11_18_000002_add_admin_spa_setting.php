<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The switch for the admin's single-page navigation (on = no change in behaviour). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        if (! DB::table('settings')->where('group', 'performance')->where('key', 'admin_spa_enabled')->exists()) {
            DB::table('settings')->insert(['group' => 'performance', 'key' => 'admin_spa_enabled', 'value' => '1', 'type' => 'boolean', 'is_encrypted' => false]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'performance')->where('key', 'admin_spa_enabled')->delete();
    }
};
