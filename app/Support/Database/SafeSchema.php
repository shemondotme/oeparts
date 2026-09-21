<?php

namespace App\Support\Database;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Defends schema-altering migrations against a real, observed failure mode:
 * MySQL DDL isn't transactional, so if an earlier update attempt was
 * interrupted after a column/index/table was actually created but before
 * Laravel recorded the migration row — or a DB restore (full or single-table,
 * see config/backup.php's documented restore-scope limitation) put the
 * `migrations` tracking table back to an older state without reverting the
 * schema it describes — the live schema and the migration runner's
 * bookkeeping can disagree. The usual `Schema::hasColumn()`/`hasTable()`
 * pre-check reads that same disagreement and still runs the DDL, which then
 * fails with "Duplicate column/key name" or "Table already exists" and
 * blocks the whole update batch (confirmed live: v1.0.16 -> v1.0.18, the
 * `add_slug_to_products_table` migration, 2026-09-01).
 *
 * Each method here keeps the normal pre-check (the fast, correct-99%-of-
 * the-time path) AND catches the specific MySQL "already exists" error
 * codes as a fallback. If MySQL itself says the object is already there,
 * that IS proof the migration's intent is satisfied — log it and move on
 * instead of failing the batch.
 */
class SafeSchema
{
    /** MySQL error codes meaning "the object this DDL wanted to create is already there." */
    private const ALREADY_EXISTS_CODES = [
        '1050', // table already exists
        '1060', // duplicate column
        '1061', // duplicate key/index name
        '3822', // duplicate check constraint name
    ];

    public static function table(string $context, string $table, Closure $callback): void
    {
        self::guard($context, fn () => Schema::table($table, $callback));
    }

    public static function create(string $context, string $table, Closure $callback): void
    {
        self::guard($context, fn () => Schema::create($table, $callback));
    }

    public static function statement(string $context, string $sql): void
    {
        self::guard($context, fn () => DB::statement($sql));
    }

    private static function guard(string $context, Closure $ddl): void
    {
        try {
            $ddl();
        } catch (QueryException $e) {
            if (! self::isAlreadyExists($e)) {
                throw $e;
            }

            Log::warning("[migration] {$context}: DDL target already existed (schema/migrations-table desync recovered)", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function isAlreadyExists(QueryException $e): bool
    {
        return in_array((string) ($e->errorInfo[1] ?? ''), self::ALREADY_EXISTS_CODES, true);
    }
}
