<?php

namespace App\Services\Migration\Import;

use App\Support\Database\DatabaseFlavour;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * The products search index (FULLTEXT with the ngram parser) as the import sees it: does this server have one, can it
 * be dropped and built again, is it there now. Every answer comes from the server itself (information_schema), never
 * from what a previous process believed, so a resumed stage or a rollback decides from the truth.
 *
 * Not final on purpose: the tests replace it with one that records instead of running DDL on a SQLite database.
 */
class FulltextIndex
{
    public function __construct(private readonly ?Connection $connection = null) {}

    public function name(): string
    {
        return DatabaseFlavour::OEM_NGRAM_INDEX;
    }

    /** Can this server hold the index at all? (MySQL 8 yes; MariaDB and SQLite no: there the stages do nothing.) */
    public function supported(): bool
    {
        return DatabaseFlavour::supportsNgramParser($this->db());
    }

    public function exists(): bool
    {
        return $this->db()->selectOne(
            'SELECT 1 AS present FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['products', $this->name()]
        ) !== null;
    }

    public function drop(): void
    {
        $this->db()->statement('ALTER TABLE products DROP INDEX '.$this->name());
        DatabaseFlavour::forgetOemNgramIndexAnswer();
    }

    public function add(): void
    {
        $this->db()->statement('ALTER TABLE products ADD FULLTEXT INDEX '.$this->name().' (normalized_oem) WITH PARSER ngram');
        DatabaseFlavour::forgetOemNgramIndexAnswer();
    }

    /** True when the import is configured to drop the index for the load. */
    public function dropWanted(): bool
    {
        return config('migration.import.fulltext_strategy', 'keep') === 'drop_readd';
    }

    private function db(): Connection
    {
        return $this->connection ?? DB::connection();
    }
}
