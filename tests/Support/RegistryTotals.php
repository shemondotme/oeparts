<?php

namespace Tests\Support;

/**
 * The ONE place that knows how many tables the application has. Guard tests compare the real
 * migrated schema with GroupRegistry; when a migration adds a table, the registry changes in the same
 * commit and so does this file, and nothing else in the test suite hard-codes a table count.
 *
 * Total: 101 tables assigned to a group plus the shared `seo_meta` = 102. System: 22 (the framework
 * `migrations` table included, and `package_share_links` since Phase 9.5).
 */
final class RegistryTotals
{
    /** Every table in the database, `migrations` included. */
    public const TOTAL_TABLES = 102;

    /** Of those, the ones that belong to the server and never travel. */
    public const SYSTEM_TABLES = 22;
}
