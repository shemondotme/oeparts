<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;

/**
 * What the connected database server can actually do, found by asking it rather than
 * by trusting the Laravel driver name.
 *
 * A MariaDB server is normally reached through the "mysql" driver (version.json declares
 * MariaDB 10.11+ supported), so getDriverName() === 'mysql' says nothing about MySQL-only
 * features. The one that matters here is the FULLTEXT "ngram" parser behind the OEM
 * substring search: MySQL 8 has it, MariaDB does not. Assuming it made the ngram migration
 * throw on MariaDB (blocking the whole update) and made Product::scopeOemContains() run
 * MATCH ... AGAINST against an index that was never created.
 */
final class DatabaseFlavour
{
    public const OEM_NGRAM_INDEX = 'products_normalized_oem_ngram_fulltext';

    private const CACHE_KEY = 'db.oem_ngram_index_present';

    private const CACHE_SECONDS = 600;

    /** Can this server build a FULLTEXT index WITH PARSER ngram? (MySQL 8: yes; MariaDB, SQLite: no.) */
    public static function supportsNgramParser(Connection $connection): bool
    {
        if ($connection->getDriverName() !== 'mysql') {
            return false;
        }

        try {
            $row = $connection->selectOne(
                "SELECT 1 AS present FROM information_schema.PLUGINS WHERE PLUGIN_NAME = 'ngram' AND PLUGIN_STATUS = 'ACTIVE'"
            );

            return $row !== null;
        } catch (\Throwable) {
            return false; // Cannot tell: behave as if unsupported (the safe, correct-but-slower path).
        }
    }

    /**
     * Does the products table really have the ngram index? Cached for ten minutes: this is
     * asked on every OEM substring search, and under PHP-FPM a static would be per request.
     */
    public static function hasOemNgramIndex(Connection $connection): bool
    {
        if ($connection->getDriverName() !== 'mysql') {
            return false;
        }

        $lookup = static function () use ($connection): bool {
            try {
                return $connection->selectOne(
                    'SELECT 1 AS present FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                    ['products', self::OEM_NGRAM_INDEX]
                ) !== null;
            } catch (\Throwable) {
                return false;
            }
        };

        try {
            return (bool) Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, $lookup);
        } catch (\Throwable) {
            return $lookup(); // The cache store is down: ask the database directly.
        }
    }

    /** Called by the migration that creates or drops the index, so a stale answer is not served. */
    public static function forgetOemNgramIndexAnswer(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // Nothing cached to forget if the cache is unreachable.
        }
    }
}
