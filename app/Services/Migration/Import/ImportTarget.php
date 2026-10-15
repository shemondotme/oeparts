<?php

namespace App\Services\Migration\Import;

use App\Services\Instance\CanonicalHostPolicy;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * Where the imported site is going to live: the address it answers on, whether it is the real shop or a copy, and which old
 * hosts its content still names. Read from what the person chose when the run was confirmed (the options are frozen then) and
 * checked before anything is changed: a wrong address found at the last stage would cost a rollback.
 *
 * Only an address with no folder in it is supported (a site in a subdirectory is not).
 */
final class ImportTarget
{
    /**
     * @param  array<int, string>  $alsoFrom  other old hosts whose links should be rewritten too
     */
    public function __construct(
        public readonly string $url,
        public readonly string $host,
        public readonly string $launch,
        public readonly array $alsoFrom,
        public readonly string $sourceHost,
    ) {}

    /** @throws MigrationException the address is not usable */
    public static function of(ImportState $state): self
    {
        $url = trim((string) ($state->options['url'] ?? config('app.url')));
        $parts = parse_url($url);

        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';

        $launch = (string) ($state->options['launch'] ?? CanonicalHostPolicy::LIVE);

        if (! in_array($launch, [CanonicalHostPolicy::LIVE, CanonicalHostPolicy::STAGING], true)) {
            throw new MigrationException(MigrationErrorCode::SiteUrlInvalid, ['what' => 'launch']);
        }

        // A literal IP is a fine address for a TEST copy (a fresh server reached by its number); never for a live site.
        $hostOk = CanonicalHostPolicy::isBare($host) || ($launch === CanonicalHostPolicy::STAGING && CanonicalHostPolicy::isIpv4($host));

        if (! in_array($scheme, ['http', 'https'], true)
            || ! $hostOk
            || ! in_array($path, ['', '/'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new MigrationException(MigrationErrorCode::SiteUrlInvalid);
        }

        $also = [];

        foreach ((array) ($state->options['also_from'] ?? []) as $legacy) {
            $legacy = strtolower(trim((string) $legacy));

            if ($legacy === '' || ! CanonicalHostPolicy::isBare($legacy)) {
                throw new MigrationException(MigrationErrorCode::SiteUrlInvalid, ['what' => 'also_from']);
            }

            $also[$legacy] = true;
        }

        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';

        return new self($scheme.'://'.$host.$port, $host, $launch, array_keys($also), strtolower((string) ($state->package['source']['domain'] ?? '')));
    }

    /**
     * Every old host to rewrite to the new address: where the package came from plus the extras, never the new host itself.
     *
     * @return array<int, string>
     */
    public function rewriteFrom(): array
    {
        $hosts = array_filter([$this->sourceHost, ...$this->alsoFrom], fn (string $h): bool => $h !== '' && $h !== $this->host);

        return array_values(array_unique($hosts));
    }

    /** The mode the site is meant to end up in once the launch checks pass. */
    public function intent(): string
    {
        return $this->launch === CanonicalHostPolicy::STAGING ? 'staging' : 'production';
    }
}
