<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pattern redirects and 410 Gone.
 *
 * - match_type: how from_url is read — 'exact' (as before), 'wildcard' ("*" matches anything, "$1" in the
 *   destination refers to it) or 'regex'. One rule can then replace thousands of per-URL rows when a section
 *   of an old site moved.
 * - type gains '410' (Gone): "this page was removed on purpose" — tells search engines to drop the URL
 *   faster than a 404 does. A 410 rule has no destination, so to_url is stored empty.
 *
 * MySQL: ONE statement. Appending a member to an ENUM and adding a column with a default are both
 * metadata-only operations in MySQL 8 (no table copy), which matters because `redirects` can hold a row per
 * locale per discontinued product. Other drivers (SQLite in the test suite) rebuild through the Blueprint.
 * Idempotent + reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasMatchType = Schema::hasColumn('redirects', 'match_type');

        if (DB::getDriverName() === 'mysql') {
            $clauses = [];
            if (! $hasMatchType) {
                $clauses[] = "ADD COLUMN `match_type` VARCHAR(10) NOT NULL DEFAULT 'exact' AFTER `to_url`";
            }
            $clauses[] = "MODIFY COLUMN `type` ENUM('301','302','410') NOT NULL";

            SafeSchema::statement('add_match_type_and_gone_to_redirects_table', 'ALTER TABLE `redirects` '.implode(', ', $clauses));

            return;
        }

        SafeSchema::table('add_match_type_and_gone_to_redirects_table', 'redirects', function (Blueprint $table) use ($hasMatchType) {
            if (! $hasMatchType) {
                $table->string('match_type', 10)->default('exact')->after('to_url');
            }
            $table->string('type', 3)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('redirects')->where('type', '410')->delete();
            DB::statement("ALTER TABLE `redirects` MODIFY COLUMN `type` ENUM('301','302') NOT NULL");
        }

        Schema::table('redirects', function (Blueprint $table) {
            if (Schema::hasColumn('redirects', 'match_type')) {
                $table->dropColumn('match_type');
            }
        });
    }
};
