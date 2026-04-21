<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite does not support ALTER TABLE for check constraints.
            // Recreate the settings table with 'decimal' added to the type enum.
            DB::statement('CREATE TABLE settings_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                "group" VARCHAR(50) NOT NULL,
                "key" VARCHAR(100) NOT NULL,
                value TEXT,
                type VARCHAR(255) CHECK(type IN (\'string\',\'boolean\',\'integer\',\'decimal\',\'json\',\'encrypted\')) NOT NULL DEFAULT \'string\',
                is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME,
                UNIQUE ("group", "key")
            )');
            DB::statement('INSERT INTO settings_new SELECT * FROM settings');
            DB::statement('DROP TABLE settings');
            DB::statement('ALTER TABLE settings_new RENAME TO settings');
        } else {
            Schema::table('settings', function (Blueprint $table) {
                $table->enum('type', ['string', 'boolean', 'integer', 'decimal', 'json', 'encrypted'])
                      ->default('string')
                      ->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE settings_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                "group" VARCHAR(50) NOT NULL,
                "key" VARCHAR(100) NOT NULL,
                value TEXT,
                type VARCHAR(255) CHECK(type IN (\'string\',\'boolean\',\'integer\',\'json\',\'encrypted\')) NOT NULL DEFAULT \'string\',
                is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME,
                updated_at DATETIME,
                UNIQUE ("group", "key")
            )');
            DB::statement('INSERT INTO settings_new SELECT * FROM settings');
            DB::statement('DROP TABLE settings');
            DB::statement('ALTER TABLE settings_new RENAME TO settings');
        } else {
            Schema::table('settings', function (Blueprint $table) {
                $table->enum('type', ['string', 'boolean', 'integer', 'json', 'encrypted'])
                      ->default('string')
                      ->change();
            });
        }
    }
};
