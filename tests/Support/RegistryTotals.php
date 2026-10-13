<?php

namespace Tests\Support;

/**
 * The ONE place that knows how many tables the application has. Guard tests compare the real
 * migrated schema with GroupRegistry; when a migration adds a table, the registry changes in the same
 * commit and so does this file, and nothing else in the test suite hard-codes a table count.
 *
 * Total: 100 tables assigned to a group plus the shared `seo_meta` = 101. System: 21 (the framework
 * `migrations` table included). Step 9.5 adds `package_share_links` to the system group (102 and 22).
 */
final class RegistryTotals
{
    /** Every table in the database, `migrations` included. */
    public const TOTAL_TABLES = 101;

    /** Of those, the ones that belong to the server and never travel. */
    public const SYSTEM_TABLES = 21;
}
