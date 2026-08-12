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

        return Cache::remember(
            "crawler_verified:{$ip}",
            now()->addHours(self::CACHE_TTL_HOURS),
            function () use ($ip): bool {
                try {
                    return $this->verify($ip);
                } catch (\Throwable $e) {
                    return false;
                }
            }
        );
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

        return $this->forwardLookup($hostname) === $ip;
    }

    /**
     * Wrapped in a protected method (rather than called directly) so a test
     * double can stub DNS resolution — PHPUnit cannot mock PHP's built-in
     * gethostbyaddr()/gethostbyname() functions directly.
     */
    protected function reverseLookup(string $ip): string
    {
        $result = @gethostbyaddr($ip);

        return is_string($result) ? $result : '';
    }

    protected function forwardLookup(string $hostname): string
    {
        $result = @gethostbyname($hostname);

        return is_string($result) ? $result : '';
    }
}
