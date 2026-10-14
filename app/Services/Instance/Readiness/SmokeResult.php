<?php

namespace App\Services\Instance\Readiness;

/** What one smoke request came back with: the status, where it points (a redirect), and the first part of the page. */
final class SmokeResult
{
    public function __construct(
        public readonly string $path,
        public readonly int $status,
        public readonly ?string $location = null,
        public readonly string $body = '',
        public readonly ?string $error = null,
    ) {}

    public function isRedirect(): bool
    {
        return $this->status >= 300 && $this->status < 400 && $this->location !== null;
    }

    public function isServerError(): bool
    {
        return $this->status >= 500 || $this->error !== null;
    }

    /** The host a redirect points at, lower case; null when it stays on the site (a path) or this is not a redirect. */
    public function locationHost(): ?string
    {
        if (! $this->isRedirect()) {
            return null;
        }

        $host = parse_url((string) $this->location, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : null;
    }
}
