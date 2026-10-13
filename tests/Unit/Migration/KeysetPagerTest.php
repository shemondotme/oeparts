<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\Export\KeysetPager;
use App\Services\Migration\Groups\GroupRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.8: reading a table page by page by its key. No OFFSET anywhere: OFFSET re-reads every row
 * before the page, which on a million-row table makes each page slower than the last.
 */
class KeysetPagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('zz_composite', function ($table): void {
            $table->unsignedInteger('a');
            $table->string('b', 20);
            $table->unsignedInteger('c');
            $table->string('payload')->nullable();
            $table->primary(['a', 'b', 'c']);
        });

        Schema::create('zz_single', function ($table): void {
            $table->id();
            $table->string('payload')->nullable();
        });

        Schema::create('zz_nokey', function ($table): void {
            $table->string('payload')->nullable();
        });
    }

    /** @return array<int, array{a: int, b: string, c: int}> */
    private function compositeKeys(): array
    {
        $keys = [];
        foreach ([1, 2, 3] as $a) {
            foreach (['x', 'y', 'z'] as $b) {
                foreach ([1, 2, 3] as $c) {
                    $keys[] = ['a' => $a, 'b' => $b, 'c' => $c];
                }
            }
        }

        return $keys;
    }

    private function fillComposite(): void
    {
        $keys = $this->compositeKeys();
        mt_srand(7);
        shuffle($keys);

        foreach ($keys as $key) {
            DB::table('zz_composite')->insert($key + ['payload' => "{$key['a']}{$key['b']}{$key['c']}"]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function readAll(KeysetPager $pager, string $table, int $pageRows, int $pageBytes = 8_000_000, ?array $upTo = null): array
    {
        $rows = [];
        $after = null;
        $pages = 0;

        do {
            $page = $pager->page($table, $pager->keyOf($table), ['*'], $after, $pageRows, $pageBytes, $upTo);
            array_push($rows, ...$page['rows']);
            $after = $page['last'];
            $this->assertLessThan(500, ++$pages);
        } while ($page['rows'] !== []);

        return $rows;
    }

    #[Test]
    public function a_composite_primary_key_is_paged_completely_once_and_in_key_order(): void
    {
        $this->fillComposite();

        $rows = $this->readAll(new KeysetPager, 'zz_composite', 5);

        $this->assertCount(27, $rows);
        $this->assertSame($this->compositeKeys(), array_map(fn (array $r): array => ['a' => $r['a'], 'b' => $r['b'], 'c' => $r['c']], $rows));
    }

    #[Test]
    public function a_single_key_pages_in_order_whatever_the_page_size(): void
    {
        foreach (range(1, 23) as $i) {
            DB::table('zz_single')->insert(['payload' => "row {$i}"]);
        }

        foreach ([1, 2, 7, 23, 24, 1000] as $size) {
            $rows = $this->readAll(new KeysetPager, 'zz_single', $size);

            $this->assertSame(range(1, 23), array_column($rows, 'id'), "page size {$size}");
        }
    }

    #[Test]
    public function the_queries_never_use_offset_and_always_have_a_limit(): void
    {
        $this->fillComposite();
        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            if (preg_match('/from\s+["`]zz_composite["`]/i', $query->sql) === 1) {
                $sql[] = strtolower($query->sql);
            }
        });

        $this->readAll(new KeysetPager, 'zz_composite', 4);

        $this->assertNotEmpty($sql);
        foreach ($sql as $statement) {
            $this->assertStringNotContainsString('offset', $statement);
            $this->assertStringContainsString('limit', $statement);
            $this->assertStringContainsString('order by', $statement);
        }
    }

    #[Test]
    public function each_page_starts_strictly_after_the_last_key_of_the_one_before(): void
    {
        $this->fillComposite();
        $pager = new KeysetPager;

        $first = $pager->page('zz_composite', ['a', 'b', 'c'], ['*'], null, 5, 8_000_000);
        $second = $pager->page('zz_composite', ['a', 'b', 'c'], ['*'], $first['last'], 5, 8_000_000);

        $this->assertSame(['a' => 1, 'b' => 'y', 'c' => 2], $first['last'], 'the fifth key in order');
        $this->assertSame([1, 'y', 3], [$second['rows'][0]['a'], $second['rows'][0]['b'], $second['rows'][0]['c']], 'the next page starts with the very next key');
    }

    #[Test]
    public function rows_inserted_while_paging_are_picked_up_when_they_sort_after_the_cursor_and_the_high_water_mark_stops_that(): void
    {
        foreach (range(1, 6) as $i) {
            DB::table('zz_single')->insert(['payload' => "row {$i}"]);
        }
        $pager = new KeysetPager;

        // Without a high-water mark: a row added behind the cursor's position is read.
        $first = $pager->page('zz_single', ['id'], ['*'], null, 3, 8_000_000);
        DB::table('zz_single')->insert(['payload' => 'late']);
        $rest = $this->readFrom($pager, 'zz_single', $first['last'], 3);
        $this->assertSame([4, 5, 6, 7], array_column($rest, 'id'));

        // With the mark taken at the start of the table (id 7), a row added afterwards is not.
        $mark = ['id' => (int) DB::table('zz_single')->max('id')];
        DB::table('zz_single')->insert(['payload' => 'later']);
        $all = $this->readAll($pager, 'zz_single', 3, 8_000_000, $mark);
        $this->assertSame(range(1, 7), array_column($all, 'id'));
    }

    /** @return array<int, array<string, mixed>> */
    private function readFrom(KeysetPager $pager, string $table, ?array $after, int $size): array
    {
        $rows = [];
        do {
            $page = $pager->page($table, $pager->keyOf($table), ['*'], $after, $size, 8_000_000);
            array_push($rows, ...$page['rows']);
            $after = $page['last'];
        } while ($page['rows'] !== []);

        return $rows;
    }

    #[Test]
    public function a_page_is_also_capped_by_bytes_but_always_holds_at_least_one_row(): void
    {
        foreach (range(1, 10) as $i) {
            DB::table('zz_single')->insert(['payload' => str_repeat('x', 1000)]);
        }

        $page = (new KeysetPager)->page('zz_single', ['id'], ['*'], null, 10, 2500);
        $this->assertCount(3, $page['rows'], 'about 1 KB per row: the third row reaches 2.5 KB');

        $tiny = (new KeysetPager)->page('zz_single', ['id'], ['*'], null, 10, 1);
        $this->assertCount(1, $tiny['rows'], 'a single row bigger than the cap still goes through');

        $all = $this->readAll(new KeysetPager, 'zz_single', 10, 2500);
        $this->assertSame(range(1, 10), array_column($all, 'id'));
    }

    #[Test]
    public function an_empty_table_gives_an_empty_page_and_no_cursor(): void
    {
        $page = (new KeysetPager)->page('zz_single', ['id'], ['*'], null, 10, 1000);

        $this->assertSame([], $page['rows']);
        $this->assertNull($page['last']);
    }

    #[Test]
    public function only_the_requested_columns_are_selected(): void
    {
        DB::table('zz_single')->insert(['payload' => 'secret-ish']);

        $page = (new KeysetPager)->page('zz_single', ['id'], ['id'], null, 10, 1000);

        $this->assertSame([['id' => 1]], $page['rows']);
    }

    #[Test]
    public function the_key_is_discovered_from_the_schema(): void
    {
        $pager = new KeysetPager;

        $this->assertSame(['a', 'b', 'c'], $pager->keyOf('zz_composite'));
        $this->assertSame(['id'], $pager->keyOf('zz_single'));
        $this->assertSame(['role_id', 'model_id', 'model_type'], $pager->keyOf('model_has_roles'));
    }

    #[Test]
    public function a_table_without_any_usable_key_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);

        (new KeysetPager)->keyOf('zz_nokey');
    }

    #[Test]
    public function every_exportable_table_of_the_application_has_a_key_the_pager_can_find(): void
    {
        $pager = new KeysetPager;

        foreach ((new GroupRegistry)->exportableTables() as $table) {
            $this->assertNotSame([], $pager->keyOf($table), $table);
        }
    }
}
