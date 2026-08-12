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
