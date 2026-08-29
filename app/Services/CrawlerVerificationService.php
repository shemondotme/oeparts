<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Verifies a request IP genuinely belongs to a known search-engine crawler
 * (Googlebot, Bingbot) via reverse-DNS then forward-DNS confirmation — the
 * method Google/Bing themselves document as the spoof-resistant way to
 * verify their own crawlers, unlike User-Agent string matching (trivially
 * spoofable by anyone).
 *
 * A spoofed PTR record alone can't pass this check: an attacker can point
 * a reverse-DNS record for their own IP at a fake "*.googlebot.com" name,
 * but they cannot make Google's real DNS servers forward-resolve that same
 * fake hostname back to their own IP — only Google controls that.
 *
 * @see https://developers.google.com/search/docs/crawling-indexing/verifying-googlebot
 */
class CrawlerVerificationService
{
    private const CACHE_TTL_HOURS = 12;

    /**
     * A transient DNS failure (resolver timeout/unreachable) is a different
     * signal than a confirmed "not a crawler" — caching it for the full
     * CACHE_TTL_HOURS would lock a real Googlebot/Bingbot IP out of the
     * rate-limit bypass for 12h over a one-off blip. Retry much sooner.
     */
    private const EXCEPTION_CACHE_TTL_MINUTES = 5;

    /**
     * Hostname suffixes belonging to search engines whose crawlers this
     * codebase currently exempts from the storefront search rate limiter.
     */
    private const CRAWLER_HOSTNAME_SUFFIXES = [
        '.googlebot.com',
        '.google.com',
        '.googleusercontent.com',
        '.search.msn.com',
    ];

    /**
     * Is this IP a verified Googlebot/Bingbot? Cached per-IP for 12h — a DNS
     * round-trip on every request would be its own availability risk.
     *
     * Never throws: any DNS resolution failure resolves to false (treated
     * as a normal visitor, still subject to the rate limiter) rather than
     * breaking the request the bypass exists to protect.
     */
    public function isVerifiedCrawler(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        $key = "crawler_verified:{$ip}";

        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->verify($ip);
            $ttl = now()->addHours(self::CACHE_TTL_HOURS);
        } catch (\Throwable $e) {
            $result = false;
            $ttl = now()->addMinutes(self::EXCEPTION_CACHE_TTL_MINUTES);
        }

        Cache::put($key, $result, $ttl);

        return $result;
    }

    private function verify(string $ip): bool
    {
        $hostname = rtrim($this->reverseLookup($ip), '.');

        // gethostbyaddr() returns the IP unchanged (or '') when it can't
        // resolve — either way, that's "not a recognized crawler."
        if ($hostname === '' || $hostname === $ip) {
            return false;
        }

        $matchesKnownSuffix = false;
        foreach (self::CRAWLER_HOSTNAME_SUFFIXES as $suffix) {
            if (str_ends_with($hostname, $suffix)) {
                $matchesKnownSuffix = true;
                break;
            }
        }

        if (! $matchesKnownSuffix) {
            return false;
        }

        return in_array($ip, $this->forwardLookup($hostname), true);
    }

    /**
     * Wrapped in protected methods (rather than called directly) so a test
     * double can stub DNS resolution — PHPUnit cannot mock PHP's built-in
     * gethostbyaddr()/dns_get_record() functions directly.
     */
    protected function reverseLookup(string $ip): string
    {
        $result = @gethostbyaddr($ip);

        return is_string($result) ? $result : '';
    }

    /**
     * Every IPv4/IPv6 address this hostname resolves to. gethostbyname()
     * (the obvious PHP built-in for this) only ever resolves A records —
     * a real crawler request arriving over IPv6 could never forward-confirm
     * against that result, permanently failing verification on that
     * protocol alone. dns_get_record() covers both address families.
     *
     * @return array<int, string>
     */
    protected function forwardLookup(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_A + DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records
        )));
    }
}
