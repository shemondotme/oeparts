<?php

namespace Tests\Mysql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\TableChecksum;

/**
 * The SQL-side checksum the migration rehearsals compare source and target with: it has to notice
 * every kind of difference and none that does not matter (row order).
 */
class TableChecksumTest extends MysqlTestCase
{
    private const TABLE = 'zz_checksum_probe';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists(self::TABLE);
        DB::statement('CREATE TABLE '.self::TABLE.' (
            id INT UNSIGNED NOT NULL PRIMARY KEY,
            name VARCHAR(100) NULL,
            amount DECIMAL(12,2) NULL,
            payload JSON NULL,
            raw BLOB NULL,
            happened_at TIMESTAMP NULL DEFAULT NULL,
            doubled INT GENERATED ALWAYS AS (id * 2) VIRTUAL
        )');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists(self::TABLE);
        parent::tearDown();
    }

    private function row(int $id, array $overrides = []): void
    {
        DB::table(self::TABLE)->insert(array_merge([
            'id' => $id, 'name' => 'Brake pad '.$id, 'amount' => '12.50', 'payload' => '{"a":1,"b":[1,2]}',
            'raw' => "\x00\x01\xFF", 'happened_at' => '2026-03-01 10:00:00',
        ], $overrides));
    }

    #[Test]
    public function an_empty_table_has_a_stable_checksum_and_zero_rows(): void
    {
        $a = TableChecksum::of(self::TABLE);
        $b = TableChecksum::of(self::TABLE);

        $this->assertSame(0, $a['rows']);
        $this->assertSame($a['checksum'], $b['checksum']);
    }

    #[Test]
    public function the_same_rows_inserted_in_another_order_give_the_same_checksum(): void
    {
        foreach ([1, 2, 3, 4] as $id) {
            $this->row($id);
        }
        $first = TableChecksum::of(self::TABLE);

        DB::table(self::TABLE)->delete();
        foreach ([4, 2, 3, 1] as $id) {
            $this->row($id);
        }

        $this->assertSame($first, TableChecksum::of(self::TABLE));
    }

    #[Test]
    public function any_single_changed_cell_changes_the_checksum(): void
    {
        $this->row(1);
        $this->row(2);
        $base = TableChecksum::of(self::TABLE)['checksum'];

        $changes = [
            'text' => ['name' => 'Brake pad 2 '],
            'number' => ['amount' => '12.51'],
            'json' => ['payload' => '{"a":1,"b":[1,3]}'],
            'binary' => ['raw' => "\x00\x01\xFE"],
            'time' => ['happened_at' => '2026-03-01 10:00:01'],
        ];

        foreach ($changes as $what => $change) {
            DB::table(self::TABLE)->where('id', 2)->update($change);
            $this->assertNotSame($base, TableChecksum::of(self::TABLE)['checksum'], "a changed {$what} cell went unnoticed");

            DB::table(self::TABLE)->delete();
            $this->row(1);
            $this->row(2);
            $this->assertSame($base, TableChecksum::of(self::TABLE)['checksum'], 'restoring the row restores the checksum');
        }
    }

    #[Test]
    public function null_and_empty_string_and_zero_are_all_different(): void
    {
        $sums = [];

        foreach (['null' => null, 'empty' => '', 'zero' => '0', 'text null' => 'NULL'] as $label => $value) {
            DB::table(self::TABLE)->delete();
            $this->row(1, ['name' => $value]);
            $sums[$label] = TableChecksum::of(self::TABLE)['checksum'];
        }

        $this->assertCount(4, array_unique($sums), 'NULL, "", "0" and the word NULL must all differ');
    }

    #[Test]
    public function a_missing_a_duplicated_and_an_extra_row_are_all_noticed(): void
    {
        $this->row(1);
        $this->row(2);
        $base = TableChecksum::of(self::TABLE);

        DB::table(self::TABLE)->where('id', 2)->delete();
        $this->assertNotSame($base['checksum'], TableChecksum::of(self::TABLE)['checksum']);
        $this->assertSame(1, TableChecksum::of(self::TABLE)['rows']);

        $this->row(2);
        $this->row(3);
        $this->assertNotSame($base['checksum'], TableChecksum::of(self::TABLE)['checksum']);
        $this->assertSame(3, TableChecksum::of(self::TABLE)['rows']);
    }

    #[Test]
    public function a_generated_column_is_not_part_of_the_checksum_and_is_not_listed(): void
    {
        $this->row(1);

        $result = TableChecksum::of(self::TABLE);

        $this->assertNotContains('doubled', $result['columns']);
        $this->assertContains('id', $result['columns']);
    }

    #[Test]
    public function timestamps_are_rendered_in_utc_whatever_the_session_zone_was(): void
    {
        DB::statement("SET time_zone = '+00:00'");
        $this->row(1, ['happened_at' => '2026-03-01 10:00:00']);
        $utc = TableChecksum::of(self::TABLE)['checksum'];

        DB::statement("SET time_zone = '+05:00'");
        $shifted = TableChecksum::of(self::TABLE)['checksum'];
        DB::statement("SET time_zone = '+00:00'");

        $this->assertSame($utc, $shifted);
    }

    #[Test]
    public function the_server_native_checksum_agrees_that_equal_tables_are_equal(): void
    {
        $this->row(1);
        $this->row(2);
        $native = TableChecksum::native(self::TABLE);

        DB::table(self::TABLE)->where('id', 2)->update(['name' => 'changed']);
        $this->assertNotSame($native, TableChecksum::native(self::TABLE));

        DB::table(self::TABLE)->where('id', 2)->update(['name' => 'Brake pad 2']);
        $this->assertSame($native, TableChecksum::native(self::TABLE));
    }

    #[Test]
    public function an_unknown_table_is_an_error_not_an_empty_checksum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TableChecksum::of('zz_no_such_table');
    }
}
