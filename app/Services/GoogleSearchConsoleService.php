<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Search Console's own "Crawl Errors" report was removed from the API
 * years ago — the closest still-available equivalent is the Sitemaps
 * resource's own errors/warnings counts, which (together with each
 * sitemap's contents[].submitted/indexed) is exactly the "indexed vs
 * submitted, crawl errors" data this dashboard needs, and needs only one
 * endpoint (no Search Analytics date-range query required).
 *
 * Needs a real Google Cloud OAuth client (client id/secret) plus a
 * one-time authorization flow to obtain a refresh token — that flow is
 * outside this codebase (Google's own OAuth consent screen); this service
 * only performs the ongoing refresh-token -> access-token exchange, never
 * the initial authorization.
 */
class GoogleSearchConsoleService
{
    public function isConfigured(): bool
    {
        return trim((string) settings('seo.gsc_client_id', '')) !== ''
            && trim((string) settings('seo.gsc_client_secret', '')) !== ''
            && trim((string) settings('seo.gsc_refresh_token', '')) !== ''
            && trim((string) settings('seo.gsc_property_url', '')) !== '';
    }

    /**
     * @return array{submitted: int, indexed: int, errors: int, warnings: int}|array{error: string}
     */
    public function getSitemapSummary(): array
    {
        try {
            $accessToken = $this->refreshAccessToken();
            $siteUrl = rawurlencode(trim((string) settings('seo.gsc_property_url', '')));

            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->get("https://searchconsole.googleapis.com/webmasters/v3/sites/{$siteUrl}/sitemaps");

            if (! $response->successful()) {
                return ['error' => "Search Console API returned HTTP {$response->status()}"];
            }

            $submitted = 0;
            $indexed = 0;
            $errors = 0;
            $warnings = 0;

            foreach ($response->json('sitemap', []) as $sitemap) {
                $errors += (int) ($sitemap['errors'] ?? 0);
                $warnings += (int) ($sitemap['warnings'] ?? 0);

                foreach ($sitemap['contents'] ?? [] as $content) {
                    $submitted += (int) ($content['submitted'] ?? 0);
                    $indexed += (int) ($content['indexed'] ?? 0);
                }
            }

            return compact('submitted', 'indexed', 'errors', 'warnings');
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Search Analytics data lags 2-3 days behind real-time in GSC itself, so
     * the window ends 3 days ago rather than today — querying all the way to
     * "today" would just return zero rows for the most recent days rather
     * than an error, silently making the totals look artificially low.
     *
     * @return array{totalClicks:int, totalImpressions:int, avgCtr:float, avgPosition:float, topQueries:array<int,array{query:string,clicks:int,impressions:int,ctr:float,position:float}>, topPages:array<int,array{page:string,clicks:int,impressions:int}>}|array{error:string}
     */
    public function getSearchAnalytics(int $days = 28): array
    {
        try {
            $accessToken = $this->refreshAccessToken();
            $siteUrl = rawurlencode(trim((string) settings('seo.gsc_property_url', '')));
            $endpoint = "https://searchconsole.googleapis.com/webmasters/v3/sites/{$siteUrl}/searchAnalytics/query";

            $startDate = now()->subDays($days)->toDateString();
            $endDate = now()->subDays(3)->toDateString();

            $totalsResponse = Http::withToken($accessToken)->timeout(15)->post($endpoint, [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

            if (! $totalsResponse->successful()) {
                return ['error' => "Search Console API returned HTTP {$totalsResponse->status()}"];
            }

            $totals = $totalsResponse->json('rows.0', []);

            $queryRows = $this->queryRows($accessToken, $endpoint, $startDate, $endDate, 'query');
            $pageRows = $this->queryRows($accessToken, $endpoint, $startDate, $endDate, 'page');

            return [
                'totalClicks' => (int) ($totals['clicks'] ?? 0),
                'totalImpressions' => (int) ($totals['impressions'] ?? 0),
                'avgCtr' => (float) ($totals['ctr'] ?? 0),
                'avgPosition' => round((float) ($totals['position'] ?? 0), 1),
                'topQueries' => array_map(fn (array $r): array => [
                    'query' => $r['keys'][0] ?? '',
                    'clicks' => (int) ($r['clicks'] ?? 0),
                    'impressions' => (int) ($r['impressions'] ?? 0),
                    'ctr' => (float) ($r['ctr'] ?? 0),
                    'position' => round((float) ($r['position'] ?? 0), 1),
                ], $queryRows),
                'topPages' => array_map(fn (array $r): array => [
                    'page' => $r['keys'][0] ?? '',
                    'clicks' => (int) ($r['clicks'] ?? 0),
                    'impressions' => (int) ($r['impressions'] ?? 0),
                ], $pageRows),
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * The query/page breakdown is secondary to the headline totals above —
     * if this call fails (rate limit, transient error) the dashboard still
     * shows real totals rather than the whole section erroring out.
     *
     * @return array<int, array<string, mixed>>
     */
    private function queryRows(string $accessToken, string $endpoint, string $startDate, string $endDate, string $dimension): array
    {
        $response = Http::withToken($accessToken)->timeout(15)->post($endpoint, [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => [$dimension],
            'rowLimit' => 10,
        ]);

        return $response->successful() ? $response->json('rows', []) : [];
    }

    /**
     * URL Inspection — the closest live equivalent to the old "Fetch as
     * Google" tool, reporting whether Google can actually index a specific
     * URL right now (verdict/coverage/robots.txt state), not just the
     * sitemap-level aggregate counts above. The API is quota-limited (a low
     * daily cap per property), so this is deliberately never called per
     * product — callers should inspect a small, fixed set of important URLs
     * (e.g. one homepage per active locale), not the whole catalog.
     *
     * @return array{verdict:string, coverageState:?string, robotsTxtState:?string, indexingState:?string, lastCrawlTime:?string}|array{error:string}
     */
    public function inspectUrl(string $url): array
    {
        try {
            $accessToken = $this->refreshAccessToken();
            $siteUrl = trim((string) settings('seo.gsc_property_url', ''));

            $response = Http::withToken($accessToken)->timeout(15)->post(
                'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect',
                ['inspectionUrl' => $url, 'siteUrl' => $siteUrl]
            );

            if (! $response->successful()) {
                return ['error' => "Search Console API returned HTTP {$response->status()}"];
            }

            $result = $response->json('inspectionResult.indexStatusResult', []);

            return [
                'verdict' => $result['verdict'] ?? 'UNKNOWN',
                'coverageState' => $result['coverageState'] ?? null,
                'robotsTxtState' => $result['robotsTxtState'] ?? null,
                'indexingState' => $result['indexingState'] ?? null,
                'lastCrawlTime' => $result['lastCrawlTime'] ?? null,
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function refreshAccessToken(): string
    {
        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => settings('seo.gsc_client_id', ''),
            'client_secret' => settings('seo.gsc_client_secret', ''),
            'refresh_token' => settings('seo.gsc_refresh_token', ''),
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to refresh Google OAuth token: HTTP {$response->status()}");
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Google OAuth token refresh returned no access_token');
        }

        return $token;
    }
}
