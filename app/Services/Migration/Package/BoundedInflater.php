<?php

namespace App\Services\Migration\Package;

use App\Services\Migration\Package\Exceptions\CorruptException;
use App\Services\Migration\Package\Exceptions\LimitExceededException;

/**
 * The ONLY place a package's compressed data may be inflated.
 *
 * The compressed size says nothing about the decompressed size (a 1 MB file of gzip can expand to
 * a gigabyte or more), so a package carries the expected decompressed length of every compressed
 * entry inside its authenticated table of contents, and this class refuses to produce a single byte
 * beyond it. Three more things plain gzdecode() gets wrong or cannot do:
 *
 *  - It stops after the FIRST gzip member and silently drops the rest, while the file backup
 *    appends one member per 1 MiB block. This class restarts at the end of each member.
 *  - It holds the whole result in memory. Here the compressed input is fed in small slices
 *    (4 KiB, at most about 4 MB of output per step) and every slice of output goes straight to a sink.
 *  - It accepts a truncated stream or trailing garbage. Here both are corruption, and the total
 *    must equal the announced length exactly, not merely stay under it.
 */
final class BoundedInflater
{
    /** Compressed bytes fed to zlib per step: bounds the output of one step to about 4 MB. */
    public const INPUT_SLICE = 4096;

    private const READ_CHUNK = 65536;

    private ?\InflateContext $context = null;

    private int $produced = 0;

    private bool $finished = false;

    public function __construct(private readonly int $expectedRawLength)
    {
        if ($expectedRawLength < 0 || $expectedRawLength > PackageLimits::MAX_ENTRY_RAW_BYTES) {
            throw new \InvalidArgumentException('The expected raw length is out of range.');
        }
    }

    /**
     * Push-style use: hand over compressed bytes as they arrive (for example frame by frame), then
     * call finish(). One inflater serves one entry.
     *
     * @param  callable(string): void  $sink  receives the decompressed bytes in slices
     *
     * @throws CorruptException|LimitExceededException
     */
    public function feed(string $compressed, callable $sink): void
    {
        if ($this->finished) {
            throw new \LogicException('This inflater is already finished.');
        }

        for ($position = 0, $total = strlen($compressed); $position < $total; $position += self::INPUT_SLICE) {
            $slice = substr($compressed, $position, self::INPUT_SLICE);

            // A slice can hold the end of one member and the start of the next.
            while ($slice !== '') {
                $this->context ??= inflate_init(ZLIB_ENCODING_GZIP);
                $readBefore = inflate_get_read_len($this->context);

                $output = @inflate_add($this->context, $slice, ZLIB_NO_FLUSH);
                if ($output === false) {
                    throw new CorruptException('The compressed data is not valid gzip.', 'compressed data');
                }

                if ($output !== '') {
                    $this->produced += strlen($output);

                    if ($this->produced > $this->expectedRawLength) {
                        throw new LimitExceededException("The data decompresses to more than the announced {$this->expectedRawLength} bytes.");
                    }

                    $sink($output);
                }

                if (inflate_get_status($this->context) === ZLIB_STREAM_END) {
                    $consumed = inflate_get_read_len($this->context) - $readBefore;
                    $slice = (string) substr($slice, $consumed);
                    $this->context = null;

                    continue;
                }

                $slice = '';
            }
        }
    }

    /**
     * The end of the input: the stream must stop between members and the total must be exactly the
     * announced length.
     *
     * @return int bytes produced
     *
     * @throws CorruptException
     */
    public function finish(): int
    {
        $this->finished = true;

        if ($this->context !== null) {
            throw new CorruptException('The compressed data ends in the middle of a gzip member.', 'compressed data');
        }

        if ($this->produced !== $this->expectedRawLength) {
            throw new CorruptException("The data decompresses to {$this->produced} bytes, but {$this->expectedRawLength} were announced.", 'compressed data');
        }

        return $this->produced;
    }

    /**
     * @param  resource  $in  compressed bytes
     * @param  callable(string): void  $sink  receives the decompressed bytes in slices
     * @return int bytes produced (always equal to the expected length, or an exception)
     *
     * @throws CorruptException|LimitExceededException
     */
    public function inflateStream($in, callable $sink): int
    {
        while (($chunk = fread($in, self::READ_CHUNK)) !== false && $chunk !== '') {
            $this->feed($chunk, $sink);
        }

        return $this->finish();
    }

    /**
     * Convenience for small entries: the whole result as a string.
     *
     * @throws CorruptException|LimitExceededException
     */
    public function inflateString(string $compressed): string
    {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $compressed);
        rewind($stream);

        $result = '';

        try {
            $this->inflateStream($stream, function (string $part) use (&$result): void {
                $result .= $part;
            });
        } finally {
            fclose($stream);
        }

        return $result;
    }
}
