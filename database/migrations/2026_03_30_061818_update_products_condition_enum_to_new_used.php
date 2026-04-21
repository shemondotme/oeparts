<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $usedValues = ['used_grade_a', 'used_grade_b', 'used_grade_c', 'remanufactured', 'aftermarket', 'new_old_stock'];
        $placeholders = implode(',', array_fill(0, count($usedValues), '?'));

        DB::statement(
            "UPDATE `products` SET `condition` = ? WHERE `condition` IN ({$placeholders})",
            array_merge(['used'], $usedValues)
        );

        // MySQL enforces ENUM; SQLite stores as TEXT and doesn't need ALTER
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `products` MODIFY `condition` ENUM('new','used') NOT NULL DEFAULT 'new'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `products` MODIFY `condition` ENUM('new','used_grade_a','used_grade_b','used_grade_c','remanufactured','aftermarket','new_old_stock') NOT NULL DEFAULT 'new'");
        }
    }
};
