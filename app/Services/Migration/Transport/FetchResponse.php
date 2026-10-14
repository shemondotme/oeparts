<?php

namespace App\Services\Migration\Transport;

/** The outcome of one bounded range request: 200 or 206 with the bytes already in the sink, or 416 with the real size. */
final class FetchResponse
{
    /** @param  array<string, string>  $headers */
    public function __construct(
        public readonly int $status,
        public readonly int $bytesWritten,
        public readonly ?int $total,
        public readonly ?int $rangeStart,
        public readonly ?int $rangeEnd,
        public readonly array $headers,
        public readonly string $finalUrl,
        public readonly int $redirects,
    ) {}

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? '';

        return $value === '' ? null : $value;
    }

    public function isRangeNotSatisfiable(): bool
    {
        return $this->status === 416;
    }
}
