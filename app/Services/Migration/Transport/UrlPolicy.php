<?php

namespace App\Services\Migration\Transport;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * The only judge of "may this server fetch that URL" (invariant I3). It is strict and canonical: anything unusual is
 * refused rather than interpreted, because every parser disagrees about unusual URLs and an attacker needs only one
 * disagreement. The name is resolved ONCE here; every answer must be public; one address is chosen to pin.
 *
 * What it does not do: follow redirects (the fetcher validates each hop through here again) and open a connection.
 * The protection cannot be switched off from the admin panel; the environment-only OE_MIGRATION_ALLOW_PRIVATE_HOSTS
 * relaxes the address check for an intranet or a rehearsal.
 */
class UrlPolicy
{
    private const REFUSED_SUFFIXES = ['localhost', 'local', 'localdomain', 'internal', 'intranet', 'lan', 'corp', 'home', 'private', 'home.arpa', 'onion', 'invalid', 'arpa'];

    public function __construct(
        private readonly HostResolver $resolver,
        private readonly IpClassifier $classifier,
    ) {}

    /** @throws MigrationException url_not_https or url_blocked (the reason is a parameter, for the log) */
    public function validate(string $url): ValidatedUrl
    {
        $url = trim($url);
        $maxLength = (int) config('migration.transport.max_url_length', 2048);

        if ($url === '' || strlen($url) > $maxLength) {
            throw $this->blocked('empty or too long');
        }

        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            throw $this->blocked('control characters, spaces or backslashes');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'], $parts['scheme'])) {
            throw $this->blocked('not a web address');
        }

        if (strtolower((string) $parts['scheme']) !== 'https') {
            throw new MigrationException(MigrationErrorCode::UrlNotHttps);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw $this->blocked('user name or password in the address');
        }

        $port = (int) ($parts['port'] ?? 443);

        if (! in_array($port, (array) config('migration.transport.allowed_ports', [443]), true)) {
            throw $this->blocked("port {$port} is not allowed");
        }

        $host = $this->canonicalHost((string) $parts['host']);
        $this->assertHostAllowed($host);

        $addresses = $this->addressesOf($host);
        $this->assertPublic($host, $addresses);

        $pathAndQuery = ($parts['path'] ?? '/') === '' ? '/' : ($parts['path'] ?? '/');

        if (isset($parts['query']) && $parts['query'] !== '') {
            $pathAndQuery .= '?'.$parts['query'];
        }

        $hostText = str_contains($host, ':') ? '['.$host.']' : $host;

        return new ValidatedUrl(
            'https://'.$hostText.($port === 443 ? '' : ':'.$port).$pathAndQuery,
            $host,
            $port,
            $pathAndQuery,
            $this->choosePin($addresses),
            $addresses,
        );
    }

    /** ASCII, lower case, no trailing dot; an IP literal in canonical form only. */
    private function canonicalHost(string $host): string
    {
        if (str_starts_with($host, '[')) {
            $inner = substr($host, 1, -1);

            if (! str_ends_with($host, ']') || ! filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw $this->blocked('malformed IPv6 address');
            }

            return strtolower($inner);
        }

        if ($host === '' || str_ends_with($host, '.') || str_starts_with($host, '.') || str_contains($host, '..')) {
            throw $this->blocked('empty label or trailing dot in the host');
        }

        if (preg_match('/[^\x20-\x7e]/', $host) === 1) {
            $ascii = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ, INTL_IDNA_VARIANT_UTS46) : false;

            if (! is_string($ascii) || $ascii === '') {
                throw $this->blocked('host name that cannot be converted safely');
            }

            $host = $ascii;
        }

        $host = strtolower($host);

        // A last label that is a number (decimal or 0x...) is how 2130706433, 0x7f.1 and 127.1 spell an address: only the
        // canonical dotted quad is accepted.
        $labels = explode('.', $host);
        $last = (string) end($labels);

        if (preg_match('/^(0x[0-9a-f]*|[0-9]+)$/', $last) === 1) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw $this->blocked('non-canonical numeric address');
            }

            return $host;
        }

        if (strlen($host) > 253 || preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/', $host) !== 1) {
            throw $this->blocked('not a valid host name');
        }

        return $host;
    }

    private function assertHostAllowed(string $host): void
    {
        $isLiteral = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $private = (bool) config('migration.transport.allow_private_hosts', false);

        if (! $isLiteral && ! $private) {
            if (! str_contains($host, '.')) {
                throw $this->blocked('a name without a domain');
            }

            foreach (self::REFUSED_SUFFIXES as $suffix) {
                if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                    throw $this->blocked("a name under .{$suffix}");
                }
            }
        }

        $allowed = array_filter((array) config('migration.transport.allowed_hosts', []));

        if ($allowed === []) {
            return;
        }

        foreach ($allowed as $entry) {
            $entry = strtolower((string) $entry);

            if ($host === $entry || str_ends_with($host, '.'.$entry)) {
                return;
            }
        }

        throw $this->blocked('host is not on the allowed list');
    }

    /** @return list<string> */
    private function addressesOf(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = array_values(array_unique($this->resolver->resolve($host)));

        if ($addresses === []) {
            throw $this->blocked('the name does not resolve');
        }

        return $addresses;
    }

    /** @param  list<string>  $addresses */
    private function assertPublic(string $host, array $addresses): void
    {
        if ((bool) config('migration.transport.allow_private_hosts', false)) {
            return;
        }

        foreach ($addresses as $address) {
            $reason = $this->classifier->blockedReason($address);

            if ($reason !== null) {
                throw $this->blocked("{$host} points at a {$reason} address");
            }
        }
    }

    /** IPv4 first (the more reliably routed on shared hosting), then IPv6, each in the order DNS gave them. */
    private function choosePin(array $addresses): string
    {
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $address;
            }
        }

        return $addresses[0];
    }

    private function blocked(string $reason): MigrationException
    {
        return new MigrationException(MigrationErrorCode::UrlBlocked, ['reason' => $reason]);
    }
}
