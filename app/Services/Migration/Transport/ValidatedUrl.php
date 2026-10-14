<?php

namespace App\Services\Migration\Transport;

/**
 * A URL that passed UrlPolicy: the canonical text to request, and the ONE address to connect to. The fetcher pins that
 * address, so the name cannot point somewhere else between the check and the connection (DNS rebinding).
 */
final class ValidatedUrl
{
    /** @param  list<string>  $addresses Every address the host resolved to (all of them were public). */
    public function __construct(
        public readonly string $url,
        public readonly string $host,
        public readonly int $port,
        public readonly string $pathAndQuery,
        public readonly string $pinnedAddress,
        public readonly array $addresses,
    ) {}
}
