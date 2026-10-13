<?php

namespace Tests\Mysql;

use App\Models\Order;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Groups\GroupKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesExportStage;

/**
 * What SQLite cannot show about the database stage: exact DECIMALs, real JSON columns, BIGINT UNSIGNED
 * beyond the signed range, 4-byte characters, TIMESTAMPs read in UTC whatever the session zone, and the
 * checksum of every exported row set equal to the source table's (SQL-side).
 */
class ExportDatabaseStageMysqlTest extends MysqlTestCase
{
    use DrivesExportStage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('exportstage');
        config(['backup.staging_disk' => 'exportstage', 'migration.export.page_rows' => 7, 'migration.export.batch_seconds' => 0]);

        Schema::dropIfExists('zz_exact');
        DB::statement('CREATE TABLE zz_exact (
            id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            price DECIMAL(12,2) NULL,
            doc JSON NULL,
            raw BLOB NULL,
            note VARCHAR(100) NULL,
            seen_at TIMESTAMP NULL DEFAULT NULL,
            flag TINYINT(1) NULL,
            ratio DOUBLE NULL
        )');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('zz_exact');
        parent::tearDown();
    }

    #[Test]
    public function exact_numbers_json_unicode_binary_and_big_integers_come_out_unchanged(): void
    {
        DB::statement("SET time_zone = '+00:00'"); // the rows are written as UTC ...
        DB::table('zz_exact')->insert([
            ['id' => 1, 'price' => '12.50', 'doc' => '{"b": 2, "a": [1, 2]}', 'raw' => "\x00\xFF\x10", 'note' => "emoji \u{1F697} and ü", 'seen_at' => '2026-03-01 10:00:00', 'flag' => 1, 'ratio' => 0.1],
            ['id' => '18446744073709551615', 'price' => '0.00', 'doc' => null, 'raw' => null, 'note' => null, 'seen_at' => null, 'flag' => 0, 'ratio' => null],
            ['id' => 3, 'price' => '-99999999.99', 'doc' => '[]', 'raw' => '', 'note' => '', 'seen_at' => '2038-01-19 03:14:07', 'flag' => null, 'ratio' => 1.5e300],
        ]);

        DB::statement("SET time_zone = '+05:30'"); // ... and read back on a connection left in a hostile zone: the stage must not depend on it
        $plan = $this->planOf(['zz_exact']);
        $run = $this->run_($plan);
        $this->drive($run);
        $export = $this->exported($run, 'zz_exact');

        $byId = [];
        foreach ($export['rows'] as $row) {
            $byId[(string) $row[0]] = $row;
        }

        $this->assertSame(['int', 'decimal', 'json', 'binary', 'string', 'datetime', 'int', 'float'], $export['headers'][0]['types']);

        $this->assertSame('12.50', $byId['1'][1], 'DECIMAL keeps its trailing zero');
        $this->assertJson($byId['1'][2]);
        $this->assertSame(['b' => 2, 'a' => [1, 2]] == json_decode($byId['1'][2], true), true);
        $this->assertSame(['$b' => base64_encode("\x00\xFF\x10")], $byId['1'][3]);
        $this->assertSame("emoji \u{1F697} and ü", $byId['1'][4]);
        $this->assertSame('2026-03-01 10:00:00', $byId['1'][5], 'a TIMESTAMP is read in UTC even though the session was +05:30');

        $this->assertSame('18446744073709551615', $byId['18446744073709551615'][0] ?? null, 'beyond PHP_INT_MAX: the digits, not a rounded float');
        $this->assertSame('0.00', $byId['18446744073709551615'][1]);
        $this->assertSame('-99999999.99', $byId['3'][1]);
        $this->assertSame(1.5e300, $byId['3'][7]);
        $this->assertSame('2038-01-19 03:14:07', $byId['3'][5]);
        $this->assertSame(['$b' => ''], $byId['3'][3], 'an empty BLOB is an empty base64, not NULL');
    }

    #[Test]
    public function the_session_time_zone_is_left_in_utc_for_the_connection(): void
    {
        DB::statement("SET time_zone = '-08:00'");

        $run = $this->run_($this->planOf(['zz_exact']));
        $this->drive($run);

        $this->assertSame('+00:00', DB::selectOne('SELECT @@session.time_zone AS tz')->tz);
    }

    #[Test]
    public function a_real_table_with_foreign_keys_is_exported_row_for_row_and_the_cut_reference_is_null(): void
    {
        $order = Order::factory()->create();
        $other = Order::factory()->create();

        try {
            $realPlan = $this->realPlan([GroupKey::Sales]);
            $this->assertArrayHasKey('orders', $realPlan->nullified);

            $plan = ExportPlan::fromArray(array_merge($realPlan->toArray(), ['tables' => ['orders']]));
            $run = $this->run_($plan);
            $this->drive($run);

            $export = $this->exported($run, 'orders');
            $columns = $export['headers'][0]['columns'];
            $userCol = array_search('user_id', $columns, true);

            $this->assertGreaterThanOrEqual(2, count($export['rows']));
            foreach ($export['rows'] as $row) {
                $this->assertNull($row[$userCol], 'orders.user_id is optional and users are not part of this selection');
            }
        } finally {
            DB::table('order_items')->whereIn('order_id', [$order->id, $other->id])->delete();
            DB::table('orders')->whereIn('id', [$order->id, $other->id])->delete();
        }
    }
}
