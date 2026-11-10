<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Redirect groups and expiry.
 *
 * - group_name: a free label ("Site migration 2026", "Black Friday") so a batch of rules can be found, exported,
 *   switched off or removed together instead of hunting through thousands of rows.
 * - expires_at: a rule stops working at this moment (a campaign link, a temporary 302). Null = never.
 *
 * MySQL: ONE statement (a nullable column and an index add are cheap on MySQL 8 and `redirects` can be large).
 * Idempotent + reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasGroup = Schema::hasColumn('redirects', 'group_name');
        $hasExpiry = Schema::hasColumn('redirects', 'expires_at');

        if ($hasGroup && $hasExpiry) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            $clauses = [];
            if (! $hasGroup) {
                $clauses[] = 'ADD COLUMN `group_name` VARCHAR(100) NULL AFTER `match_type`';
                $clauses[] = 'ADD INDEX `redirects_group_name_index` (`group_name`)';
            }
            if (! $hasExpiry) {
                $clauses[] = 'ADD COLUMN `expires_at` TIMESTAMP NULL AFTER `is_active`';
            }

            SafeSchema::statement('add_group_and_expiry_to_redirects_table', 'ALTER TABLE `redirects` '.implode(', ', $clauses));

            return;
        }

        SafeSchema::table('add_group_and_expiry_to_redirects_table', 'redirects', function (Blueprint $table) use ($hasGroup, $hasExpiry) {
            if (! $hasGroup) {
                $table->string('group_name', 100)->nullable()->index();
            }
            if (! $hasExpiry) {
                $table->timestamp('expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            if (Schema::hasColumn('redirects', 'group_name')) {
                $table->dropIndex('redirects_group_name_index');
                $table->dropColumn('group_name');
            }
            if (Schema::hasColumn('redirects', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
