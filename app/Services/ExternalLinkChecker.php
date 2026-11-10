<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Checks the links to OTHER websites in pages and posts — the part LinkAuditService deliberately leaves out of
 * the instant audit page because it needs the network.
 *
 * Run from a weekly scheduled command or the audit page's button (both off the request that renders the page);
 * the outcome is kept and shown in the audit.
 *
 * Careful by design:
 *  - polite: one address at a time, a short pause between two requests to the same site, a hard cap on how many
 *    addresses one run visits, short timeouts;
 *  - safe: only http(s) to public addresses (never localhost / private networks — a link in content is typed by a
 *    person, but the server must not be steerable into its own network);
 *  - fair: a HEAD request first, then a tiny GET for sites that refuse HEAD; a site that blocks bots (403, 429…)
 *    is counted as "could not verify", never reported as broken; a failure is retried once before it is believed.
 */
final class ExternalLinkChecker
{
    public const CACHE_KEY = 'seo-audit:external-links';

    /** Addresses visited per run. */
    public const MAX_URLS = 400;

    private const USER_AGENT = 'Mozilla/5.0 (compatible; LinkChecker/1.0)';

    /** HTTP statuses that mean "the site would not let a bot see it", not "the page is gone". */
    private const UNVERIFIABLE = [401, 403, 406, 407, 409, 418, 423, 429, 451, 999];

    /** @param  null|\Closure(string): bool  $isPublicHost  test seam; the real check resolves the name */
    public function __construct(
        private readonly LinkAuditService $links,
        private readonly ?\Closure $isPublicHost = null,
        private readonly int $pauseMs = 250,
    ) {}

    /**
     * @return array{
     *     checked_at: string, checked: int, unverified: int, truncated: bool,
     *     broken: list<array{href: string, reason: string, sources: list<array{label: string, edit_url: string}>}>
     * }
     */
    public function run(): array
    {
        $found = $this->links->externalHrefs();
        $truncated = count($found) > self::MAX_URLS;
        $urls = array_slice(array_keys($found), 0, self::MAX_URLS);

        $broken = [];
        $unverified = 0;
        $lastRequestAt = [];

        foreach ($urls as $href) {
            $url = str_starts_with($href, '//') ? 'https:'.$href : $href;
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));

            // Polite: never hammer one site.
            if ($this->pauseMs > 0 && isset($lastRequestAt[$host])) {
                $wait = $this->pauseMs * 1000 - (int) ((microtime(true) - $lastRequestAt[$host]) * 1_000_000);
                if ($wait > 0) {
                    usleep($wait);
                }
            }
            $lastRequestAt[$host] = microtime(true);

            $verdict = $this->check($url, $host);

            if ($verdict === null) {
                continue;
            }
            if ($verdict === 'unverified') {
                $unverified++;

                continue;
            }

            $broken[] = ['href' => $href, 'reason' => $verdict, 'sources' => $found[$href]];
        }

        $result = [
            'checked_at' => now()->toIso8601String(),
            'checked' => count($urls),
            'unverified' => $unverified,
            'truncated' => $truncated,
            'broken' => $broken,
        ];

        Cache::forever(self::CACHE_KEY, $result);

        return $result;
    }

    /** The last run's outcome, or null when it has never run. */
    public static function last(): ?array
    {
        $result = Cache::get(self::CACHE_KEY);

        return is_array($result) ? $result : null;
    }

    /** @return string|'unverified'|null null = fine; 'unverified' = could not tell; any other string = why it is broken */
    private function check(string $url, string $host): ?string
    {
        if (preg_match('#^https?://#i', $url) !== 1 || $host === '') {
            return null;
        }

        if (! $this->publicHost($host)) {
            return $this->resolves($host)
                ? 'unverified'                       // a private / internal address: never fetched
                : 'the website does not exist (its name does not resolve)';
        }

        $lastError = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(8)->connectTimeout(4)->head($url);

                // Many sites refuse HEAD — ask for the first byte only.
                if (in_array($response->status(), [400, 403, 404, 405, 501], true)) {
                    $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Range' => 'bytes=0-0'])->timeout(8)->connectTimeout(4)->get($url);
                }

                $status = $response->status();

                if ($status < 400) {
                    return null;
                }
                if (in_array($status, self::UNVERIFIABLE, true)) {
                    return 'unverified';
                }
                if ($status === 404 || $status === 410) {
                    return "the page was not found ({$status})";
                }
                if ($status >= 500) {
                    $lastError = "the website reported an error ({$status})";

                    continue; // maybe a hiccup — one more try
                }

                return 'unverified';
            } catch (ConnectionException $e) {
                $lastError = 'the website could not be reached';
            } catch (\Throwable $e) {
                return 'unverified';
            }
        }

        return $lastError;
    }

    private function publicHost(string $host): bool
    {
        if ($this->isPublicHost) {
            return ($this->isPublicHost)($host);
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return false; // does not resolve
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /** Whether the name resolves at all (to tell "private address" from "no such site"). */
    private function resolves(string $host): bool
    {
        if ($this->isPublicHost) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false || gethostbyname($host) !== $host;
    }
}
