<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every column and index this release adds to `products`, in ONE statement.
 *
 * `products` carries a FULLTEXT (ngram) index, which rules out MySQL's INSTANT
 * and INPLACE algorithms: EVERY ALTER TABLE on it copies the whole table and
 * rebuilds that index. At the real catalog's ~1M rows one such ALTER takes
 * minutes (measured ~6 min each on a million-product rehearsal), all of it
 * inside the update's maintenance window — and the release used to issue five:
 * the slug column, the slug index, and three PDP columns from separate
 * migrations.
 *
 * Laravel's Blueprint cannot combine them: it compiles every added column
 * into its own `alter table` statement, so even a single Schema::table() call
 * with four columns rebuilds the table four times (found the hard way — the
 * first version of this migration used Blueprint and saved nothing). On MySQL
 * the whole set therefore goes in as one hand-written ALTER; other drivers
 * (SQLite in the test suite, MariaDB with its own JSON handling) use the
 * Blueprint, which is correct if not optimal.
 *
 * The individual migrations that own these columns (add_slug_to_products_table,
 * add_pdp_fields_to_products_table) are unchanged and still run afterwards, but
 * find nothing left to do — so a fresh database, a half-migrated one and an
 * already-migrated one all converge on the same schema. Only what is actually
 * missing goes into the statement, so a database where some of it already
 * exists (an earlier interrupted update) is handled too.
 *
 * Idempotent + reversible (rule #42).
 */
return new class extends Migration
{
    /** Column definitions in table order, identical to what the individual migrations create. */
    private const MYSQL_COLUMNS = [
        'slug' => 'ADD COLUMN `slug` VARCHAR(220) NULL AFTER `normalized_oem`',
        'specifications' => 'ADD COLUMN `specifications` JSON NULL AFTER `description`',
        'warranty_months' => 'ADD COLUMN `warranty_months` SMALLINT UNSIGNED NULL AFTER `moq`',
        // Placed after warranty_months, which MySQL resolves within the same ALTER because
        // that column is added just before it.
        'video_url' => 'ADD COLUMN `video_url` VARCHAR(500) NULL AFTER `warranty_months`',
    ];

    public function up(): void
    {
        $missing = array_values(array_filter(
            array_keys(self::MYSQL_COLUMNS),
            fn (string $column) => ! Schema::hasColumn('products', $column)
        ));
        $needsSlugIndex = ! Schema::hasIndex('products', ['slug']);

        if ($missing === [] && ! $needsSlugIndex) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            SafeSchema::statement(
                'add_release_2_0_product_columns_in_one_pass',
                $this->mysqlStatement($missing, $needsSlugIndex)
            );

            return;
        }

        SafeSchema::table('add_release_2_0_product_columns_in_one_pass', 'products', function (Blueprint $table) use ($missing, $needsSlugIndex) {
            if (in_array('slug', $missing, true)) {
                $table->string('slug', 220)->nullable()->after('normalized_oem');
            }
            if (in_array('specifications', $missing, true)) {
                $table->json('specifications')->nullable()->after('description');
            }
            if (in_array('warranty_months', $missing, true)) {
                $table->unsignedSmallInteger('warranty_months')->nullable()->after('moq');
            }
            if (in_array('video_url', $missing, true)) {
                $table->string('video_url', 500)->nullable()->after('warranty_months');
            }
            if ($needsSlugIndex) {
                $table->index('slug');
            }
        });
    }

    /**
     * The single MySQL statement for whatever is missing. Public so a test can pin the exact SQL
     * without a MySQL server (the suite runs on SQLite).
     *
     * @param  list<string>  $missingColumns
     */
    public function mysqlStatement(array $missingColumns, bool $needsSlugIndex): string
    {
        $clauses = [];
        foreach (self::MYSQL_COLUMNS as $column => $clause) {
            if (in_array($column, $missingColumns, true)) {
                $clauses[] = $clause;
            }
        }
        if ($needsSlugIndex) {
            $clauses[] = 'ADD INDEX `products_slug_index` (`slug`)';
        }

        return 'ALTER TABLE `products` '.implode(', ', $clauses);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasIndex('products', ['slug'])) {
                $table->dropIndex(['slug']);
            }
            foreach (['video_url', 'warranty_months', 'specifications', 'slug'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
