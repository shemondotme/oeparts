<?php

namespace Tests\Unit\Cleanup;

use App\Services\Cleanup\ChunkedDeleter;
use App\Services\Cleanup\WindowedCounter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ChunkedDeleterTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 9000;

    private const NEW = 3000;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cleanup_scratch', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->nullable();
            $table->string('note')->nullable();
        });

        $rows = [];
        for ($i = 1; $i <= self::OLD + self::NEW; $i++) {
            $rows[] = ['created_at' => $i <= self::OLD ? now()->subDays(100) : now()->subDay(), 'note' => "r{$i}"];
        }
        foreach (array_chunk($rows, 1000) as $batch) {
            DB::table('cleanup_scratch')->insert($batch);
        }
    }

    private function candidates(): \Closure
    {
        $cutoff = now()->subDays(30);

        return fn () => DB::table('cleanup_scratch')->where('created_at', '<', $cutoff);
    }

    private function maxId(): int
    {
        return (int) DB::table('cleanup_scratch')->max('id');
    }

    #[Test]
    public function it_deletes_every_qualifying_row_and_only_those(): void
    {
        $state = [];
        $done = (new ChunkedDeleter)->step('cleanup_scratch', 'id', $this->candidates(), $this->maxId(), $state, microtime(true) + 60, 1000, 0);

        $this->assertTrue($done);
        $this->assertSame(self::OLD, $state['deleted']);
        $this->assertSame(0, $state['failed']);
        $this->assertSame(self::NEW, DB::table('cleanup_scratch')->count());
        $this->assertSame(0, DB::table('cleanup_scratch')->where('created_at', '<', now()->subDays(30))->count());
    }

    #[Test]
    public function an_expired_deadline_still_makes_progress_so_a_run_can_never_stall(): void
    {
        $state = [];

        $done = (new ChunkedDeleter)->step('cleanup_scratch', 'id', $this->candidates(), $this->maxId(), $state, microtime(true) - 1, 500, 0);

        $this->assertFalse($done, 'the budget ran out before the end');
        $this->assertGreaterThan(0, $state['deleted'], 'at least one chunk must have been processed');
    }

    #[Test]
    public function it_resumes_from_the_checkpoint_across_many_short_ticks(): void
    {
        $state = [];
        $ticks = 0;
        $deleter = new ChunkedDeleter;

        do {
            $done = $deleter->step('cleanup_scratch', 'id', $this->candidates(), $this->maxId(), $state, microtime(true) - 1, 500, 0);
            // Simulate the checkpoint being persisted and reloaded between requests.
            $state = json_decode((string) json_encode($state), true);
            $ticks++;
        } while (! $done && $ticks < 200);

        $this->assertTrue($done);
        $this->assertGreaterThan(1, $ticks, 'it really was spread over several ticks');
        $this->assertSame(self::OLD, $state['deleted'], 'no row deleted twice, none missed');
        $this->assertSame(self::NEW, DB::table('cleanup_scratch')->count());
    }

    #[Test]
    public function it_stops_when_asked_to_and_can_continue_later(): void
    {
        $state = [];
        $deleter = new ChunkedDeleter;
        $calls = 0;

        $done = $deleter->step('cleanup_scratch', 'id', $this->candidates(), $this->maxId(), $state, microtime(true) + 60, 500, 0, null, function () use (&$calls) {
            return ++$calls > 3; // stop after three chunks
        });

        $this->assertFalse($done);
        $this->assertSame(1500, $state['deleted']);

        $done = $deleter->step('cleanup_scratch', 'id', $this->candidates(), $this->maxId(), $state, microtime(true) + 60, 500, 0);
        $this->assertTrue($done);
        $this->assertSame(self::OLD, $state['deleted']);
    }

    #[Test]
    public function every_delete_statement_is_bounded_by_the_chunk_size(): void
    {
        $maxBindings = 0;
        DB::listen(function ($query) use (&$maxBindings) {
            if (str_starts_with(strtolower($query->sql), 'delete')) {
                $maxBindings = max($maxBindings, count($query->bindings));
            }
        });

        $state = [];
        (new ChunkedDeleter)->step('cleanup_scratch', 'id', $this->candidates(), $this->maxId(), $state, microtime(true) + 60, 700, 0);

        $this->assertGreaterThan(0, $maxBindings);
        $this->assertLessThanOrEqual(700, $maxBindings, 'no statement may touch more ids than the chunk size');
    }

    #[Test]
    public function it_never_uses_delete_limit_or_truncate(): void
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = strtolower($query->sql);
        });

        $state = [];
        (new ChunkedDeleter)->step('cleanup_scratch', 'id', $this->candidates(), $this->maxId(), $state, microtime(true) + 60, 1000, 0);

        foreach ($statements as $sql) {
            $this->assertStringNotContainsString('truncate', $sql);
            if (str_starts_with($sql, 'delete')) {
                $this->assertStringNotContainsString('limit', $sql, 'DELETE ... LIMIT is unsafe under statement-based replication');
                $this->assertStringContainsString(' in (', $sql);
            }
        }
    }

    #[Test]
    public function rows_inserted_after_the_run_started_are_left_for_the_next_run(): void
    {
        $maxId = $this->maxId();
        // An old-dated row arrives mid-run (e.g. a backfill): its id is above the frozen ceiling.
        $lateId = DB::table('cleanup_scratch')->insertGetId(['created_at' => now()->subDays(200), 'note' => 'late']);

        $state = [];
        (new ChunkedDeleter)->step('cleanup_scratch', 'id', $this->candidates(), $maxId, $state, microtime(true) + 60, 1000, 0);

        $this->assertTrue(DB::table('cleanup_scratch')->where('id', $lateId)->exists());
        $this->assertSame(1, (new WindowedCounter)->late($this->candidates(), 'id', $maxId));
    }

    #[Test]
    public function the_archive_hook_sees_exactly_the_ids_that_are_deleted(): void
    {
        $archived = [];
        $state = [];

        (new ChunkedDeleter)->step('cleanup_scratch', 'id', $this->candidates(), $this->maxId(), $state, microtime(true) + 60, 1000, 0, function (array $ids) use (&$archived) {
            array_push($archived, ...$ids);

            return count($ids);
        });

        $this->assertCount(self::OLD, $archived);
        $this->assertSame(self::OLD, $state['archive_rows']);
        $this->assertSame(self::OLD, $state['deleted']);
    }

    #[Test]
    public function a_failing_chunk_is_counted_skipped_and_eventually_aborts_the_phase(): void
    {
        $state = [];

        $this->expectException(RuntimeException::class);

        try {
            (new ChunkedDeleter)->step('cleanup_scratch', 'id', $this->candidates(), $this->maxId(), $state, microtime(true) + 60, 200, 0, function () {
                throw new RuntimeException('archive disk is full');
            });
        } finally {
            $this->assertSame(self::OLD, DB::table('cleanup_scratch')->where('created_at', '<', now()->subDays(30))->count(), 'nothing is deleted when archiving fails first');
            $this->assertGreaterThan(0, $state['failed']);
        }
    }

    #[Test]
    public function the_windowed_counter_matches_the_real_count_and_resumes(): void
    {
        $counter = new WindowedCounter(1000); // several windows, each its own tick
        $state = [];
        $ticks = 0;

        do {
            $done = $counter->step($this->candidates(), 'id', $this->maxId(), $state, microtime(true) - 1);
            $ticks++;
        } while (! $done && $ticks < 50);

        $this->assertSame(self::OLD, $state['count']);
        $this->assertGreaterThan(1, $ticks);
    }

    #[Test]
    public function sparse_ids_with_gaps_larger_than_a_window_are_still_fully_covered(): void
    {
        // Ids 1..12000 exist; delete a huge middle band so whole windows are empty.
        DB::table('cleanup_scratch')->whereBetween('id', [2000, 8000])->delete();
        $expected = DB::table('cleanup_scratch')->where('created_at', '<', now()->subDays(30))->count();

        $state = [];
        $done = (new ChunkedDeleter(500))->step('cleanup_scratch', 'id', $this->candidates(), $this->maxId(), $state, microtime(true) + 60, 300, 0);

        $this->assertTrue($done);
        $this->assertSame($expected, $state['deleted']);
        $this->assertSame(0, DB::table('cleanup_scratch')->where('created_at', '<', now()->subDays(30))->count());
    }
}
