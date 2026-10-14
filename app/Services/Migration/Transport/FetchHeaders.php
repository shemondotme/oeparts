<?php

namespace App\Services\Migration\Transport;

/** What the server answered, handed to the caller BEFORE the first byte of the body is written (so a claim can be saved first). */
final class FetchHeaders
{
    /** @param  array<string, string>  $headers a few lower-cased response headers */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly ?int $total,
        public readonly ?int $rangeStart,
        public readonly ?int $rangeEnd,
        public readonly string $host,
    ) {}

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? '';

        return $value === '' ? null : $value;
    }
}
