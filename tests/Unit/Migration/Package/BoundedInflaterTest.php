<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\BoundedInflater;
use App\Services\Migration\Package\Exceptions\CorruptException;
use App\Services\Migration\Package\Exceptions\LimitExceededException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The inflater is the only door for compressed data. Plain gzdecode() keeps only the first gzip
 * member and has no output cap; these tests pin down what this class does instead.
 */
class BoundedInflaterTest extends TestCase
{
    /** @param  array<int, string>  $parts */
    private function members(array $parts): string
    {
        return implode('', array_map(fn (string $part): string => gzencode($part, 6), $parts));
    }

    private function inflate(string $compressed, int $expected): string
    {
        return (new BoundedInflater($expected))->inflateString($compressed);
    }

    #[Test]
    public function every_member_of_a_multi_member_stream_is_inflated_where_gzdecode_drops_all_but_the_first(): void
    {
        $parts = [str_repeat('a', 5000), str_repeat('b', 7000), 'tail'];
        $compressed = $this->members($parts);

        $this->assertSame($parts[0], gzdecode($compressed), 'documents the PHP behaviour this class exists to avoid');
        $this->assertSame(implode('', $parts), $this->inflate($compressed, 12004));
    }

    #[Test]
    public function members_that_straddle_the_input_slice_boundary_are_handled(): void
    {
        $parts = [];
        for ($i = 0; $i < 40; $i++) {
            $parts[] = random_bytes(1500).str_repeat((string) ($i % 10), 3000);
        }

        $raw = implode('', $parts);

        $this->assertSame($raw, $this->inflate($this->members($parts), strlen($raw)));
    }

    #[Test]
    public function an_empty_member_between_two_others_is_fine(): void
    {
        $this->assertSame('onetwo', $this->inflate($this->members(['one', '', 'two']), 6));
    }

    #[Test]
    public function an_entry_that_expands_beyond_its_announced_length_is_stopped_early(): void
    {
        $bomb = gzencode(str_repeat("\0", 50 * 1024 * 1024), 9);
        $produced = 0;

        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $bomb);
        rewind($stream);

        try {
            (new BoundedInflater(1024))->inflateStream($stream, function (string $part) use (&$produced): void {
                $produced += strlen($part);
            });
            $this->fail('expected the limit to trip');
        } catch (LimitExceededException) {
            $this->assertLessThan(8 * 1024 * 1024, $produced + 1, 'nothing past the first step is handed to the sink');
        }
    }

    #[Test]
    public function fewer_bytes_than_announced_is_corruption(): void
    {
        $this->expectException(CorruptException::class);

        $this->inflate(gzencode('short'), 500);
    }

    #[Test]
    public function a_stream_cut_in_the_middle_of_a_member_is_corruption(): void
    {
        $compressed = $this->members([random_bytes(2000), random_bytes(2000)]);

        $this->expectException(CorruptException::class);

        $this->inflate(substr($compressed, 0, strlen($compressed) - 20), 4000);
    }

    #[Test]
    public function trailing_garbage_after_the_last_member_is_corruption(): void
    {
        $this->expectException(CorruptException::class);

        $this->inflate(gzencode('data').'not gzip at all', 4);
    }

    #[Test]
    public function bytes_that_are_not_gzip_are_corruption(): void
    {
        $this->expectException(CorruptException::class);

        $this->inflate(str_repeat('x', 100), 10);
    }

    #[Test]
    public function a_flipped_bit_in_a_member_is_corruption(): void
    {
        $compressed = gzencode(random_bytes(3000));
        $compressed[40] = chr(ord($compressed[40]) ^ 0x10);

        $this->expectException(CorruptException::class);

        $this->inflate($compressed, 3000);
    }

    #[Test]
    public function an_empty_stream_with_a_zero_announced_length_is_accepted(): void
    {
        $this->assertSame('', $this->inflate('', 0));
    }

    #[Test]
    public function an_absurd_announced_length_is_refused_at_construction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BoundedInflater(PHP_INT_MAX);
    }

    #[Test]
    public function inflating_a_large_stream_does_not_hold_it_in_memory(): void
    {
        $unit = str_repeat("\0", 1024 * 1024);
        $compressed = '';
        for ($i = 0; $i < 100; $i++) {
            $compressed .= gzencode($unit, 1);
        }

        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $compressed);
        rewind($stream);
        unset($compressed);

        $total = 100 * 1024 * 1024;
        memory_reset_peak_usage();
        $before = memory_get_peak_usage();
        $seen = 0;

        (new BoundedInflater($total))->inflateStream($stream, function (string $part) use (&$seen): void {
            $seen += strlen($part);
        });

        $growth = memory_get_peak_usage() - $before;

        $this->assertSame($total, $seen);
        $this->assertLessThan(16 * 1024 * 1024, $growth, "peak memory grew by {$growth} bytes while inflating {$total}: the output is being held");
    }

    #[Test]
    public function the_output_reaches_the_sink_in_slices_not_as_one_string(): void
    {
        $raw = str_repeat('0123456789', 3_000_000);
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, gzencode($raw, 1));
        rewind($stream);

        $largest = 0;
        $total = 0;

        (new BoundedInflater(strlen($raw)))->inflateStream($stream, function (string $part) use (&$largest, &$total): void {
            $largest = max($largest, strlen($part));
            $total += strlen($part);
        });

        $this->assertSame(strlen($raw), $total);
        $this->assertLessThan(8 * 1024 * 1024, $largest);
    }
}
