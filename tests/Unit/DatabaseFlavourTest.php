<?php

namespace Tests\Unit;

use App\Support\Database\DatabaseFlavour;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A MariaDB server is reached through Laravel's "mysql" driver, so the driver name cannot
 * say whether the FULLTEXT ngram parser exists. The server is asked instead.
 */
class DatabaseFlavourTest extends TestCase
{
    private function connection(string $driver, ?object $row = null, bool $throws = false): Connection
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn($driver);

        $expectation = $connection->shouldReceive('selectOne');
        $throws ? $expectation->andThrow(new \RuntimeException('no information_schema')) : $expectation->andReturn($row);

        return $connection;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('db.oem_ngram_index_present');
    }

    #[Test]
    public function ngram_parser_support_follows_the_server_not_the_driver_name(): void
    {
        $this->assertTrue(DatabaseFlavour::supportsNgramParser($this->connection('mysql', (object) ['present' => 1])), 'MySQL 8 lists an active ngram plugin');
        $this->assertFalse(DatabaseFlavour::supportsNgramParser($this->connection('mysql', null)), 'MariaDB reports no ngram plugin');
        $this->assertFalse(DatabaseFlavour::supportsNgramParser($this->connection('mysql', null, throws: true)), 'cannot tell -> unsupported (the safe path)');
    }

    #[Test]
    public function other_drivers_never_ask_the_server(): void
    {
        foreach (['sqlite', 'pgsql', 'mariadb'] as $driver) {
            $connection = Mockery::mock(Connection::class);
            $connection->shouldReceive('getDriverName')->andReturn($driver);
            $connection->shouldNotReceive('selectOne');

            $this->assertFalse(DatabaseFlavour::supportsNgramParser($connection), $driver);
            $this->assertFalse(DatabaseFlavour::hasOemNgramIndex($connection), $driver);
        }
    }

    #[Test]
    public function the_index_answer_is_cached_so_searches_do_not_query_information_schema_each_time(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['present' => 1]);

        $this->assertTrue(DatabaseFlavour::hasOemNgramIndex($connection));
        $this->assertTrue(DatabaseFlavour::hasOemNgramIndex($connection));
        $this->assertTrue(DatabaseFlavour::hasOemNgramIndex($connection));

        // The migration that creates or drops the index clears the answer.
        DatabaseFlavour::forgetOemNgramIndexAnswer();
        $this->assertNull(Cache::get('db.oem_ngram_index_present'));
    }

    #[Test]
    public function a_missing_index_means_the_like_fallback(): void
    {
        $this->assertFalse(DatabaseFlavour::hasOemNgramIndex($this->connection('mysql', null)));
    }
}
