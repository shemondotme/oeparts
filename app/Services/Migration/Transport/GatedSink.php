<?php

namespace App\Services\Migration\Transport;

use Psr\Http\Message\StreamInterface;

/**
 * The stream the downloaded bytes go into. It starts CLOSED: whatever arrives (a redirect page, an error page) is thrown
 * away. The fetcher opens it only after the headers passed every check, with the exact number of bytes it will accept; one
 * byte more makes it refuse the write, which stops the transfer.
 *
 * It writes into the caller's own file handle and never closes it (a stream wrapper from the HTTP library would close the
 * handle when it is garbage collected, taking the caller's file with it), and it cannot be read back.
 */
final class GatedSink implements StreamInterface
{
    private bool $open = false;

    private int $allowed = 0;

    public int $written = 0;

    public bool $exceeded = false;

    /** @param  resource  $target open for writing, positioned where the bytes belong */
    public function __construct(private $target) {}

    public function open(int $allowedBytes): void
    {
        $this->open = true;
        $this->allowed = $allowedBytes;
    }

    public function write(string $string): int
    {
        $length = strlen($string);

        if (! $this->open) {
            return $length;
        }

        if ($this->written + $length > $this->allowed) {
            $this->exceeded = true;

            return 0;
        }

        $done = fwrite($this->target, $string);

        if ($done === false) {
            return 0;
        }

        $this->written += $done;

        return $done;
    }

    public function __toString(): string
    {
        return '';
    }

    public function close(): void {}

    public function detach()
    {
        return null;
    }

    public function getSize(): int
    {
        return $this->written;
    }

    public function tell(): int
    {
        return $this->written;
    }

    public function eof(): bool
    {
        return true;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void {}

    public function rewind(): void {}

    public function isWritable(): bool
    {
        return true;
    }

    public function isReadable(): bool
    {
        return false;
    }

    public function read(int $length): string
    {
        return '';
    }

    public function getContents(): string
    {
        return '';
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }
}
