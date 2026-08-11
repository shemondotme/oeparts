<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Found during a performance investigation after the catalog grew to
 * ~100k products: every partial/substring OEM lookup (SearchService::
 * partialMatch()'s storefront fallback, ProductResource's admin table
 * search box, and its global search) runs `normalized_oem LIKE
 * "%term%"` — a leading wildcard, which can never use the existing
 * normalized_oem BTREE index. At demo-catalog size this was a
 * non-issue; at 100k rows it's a full table scan on every such
 * lookup. See Product::scopeOemContains() for the query side of this
 * fix.
 *
 * MySQL's FULLTEXT index with the built-in ngram parser supports real
 * substring matching (verified empirically against this exact
 * scenario: normalized_oem values like "06L906036L", searching for
 * "906036" or even a 2-character fragment like "4F" — both correctly
 * matched only the rows actually containing that substring, nothing
 * else). SQLite (the test DB) has no equivalent — this migration is a
 * no-op there, matching Product::scopeOemContains()'s driver-aware
 * fallback to LIKE for tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // MySQL DDL isn't transactional — if a prior run of this migration
        // was interrupted after the ALTER TABLE succeeded but before Laravel
        // recorded the migration row (or the index was created out-of-band,
        // e.g. manual testing), a bare ADD FULLTEXT INDEX here fails with
        // "Duplicate key name" and blocks every future update attempt.
        if ($this->indexExists()) {
            return;
        }

        DB::statement('ALTER TABLE products ADD FULLTEXT INDEX products_normalized_oem_ngram_fulltext (normalized_oem) WITH PARSER ngram');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! $this->indexExists()) {
            return;
        }

        DB::statement('ALTER TABLE products DROP INDEX products_normalized_oem_ngram_fulltext');
    }

    private function indexExists(): bool
    {
        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['products', 'products_normalized_oem_ngram_fulltext']
        );

        return $row !== null;
    }
};
