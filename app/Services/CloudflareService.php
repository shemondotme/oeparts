<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over Cloudflare's REST API v4 (raw HTTP, no SDK — same
 * convention as PaymentService's Airwallex/Paysera integrations). Opt-in:
 * every public method no-ops (or returns a clear "not configured" result)
 * unless performance.cloudflare_enabled is on and both credentials are set.
 *
 * Deliberately purge-only, never zone-settings-changing: this app can't
 * verify live against a real Cloudflare account, and a wrong value pushed
 * to a zone SETTING (Browser Cache TTL, Cache Level, ...) would silently
 * change how the user's actual live site behaves at the edge. Purging is
 * safe to be wrong about — worst case it's a no-op or a wasted API call,
 * never a misconfiguration. See PerformanceSettings' "Cloudflare CDN"
 * section for the read-only dashboard-settings guidance this pairs with.
 */
class CloudflareService
{
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

    public function isConfigured(): bool
    {
        return filter_var(settings('performance.cloudflare_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && filled(settings('performance.cloudflare_zone_id'))
            && filled(settings('performance.cloudflare_api_token'));
    }

    /** @return array{success: bool, message: string} */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Cloudflare is not configured — set the Zone ID and API Token first.'];
        }

        try {
            $response = $this->client()->get(self::API_BASE.'/zones/'.$this->zoneId());

            if (! $response->successful() || ! ($response->json('success') ?? false)) {
                return ['success' => false, 'message' => $this->firstErrorMessage($response) ?? "HTTP {$response->status()}"];
            }

            $zoneName = $response->json('result.name', 'unknown zone');

            return ['success' => true, 'message' => "Connected to Cloudflare zone: {$zoneName}."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Purge specific URLs from Cloudflare's edge cache (max 30 per call per
     * Cloudflare's own API limit — chunked automatically). Silently no-ops
     * (returns success) when not configured, since this is called from
     * best-effort hooks (sitemap regeneration) that must never fail the
     * operation that triggered them over an optional CDN integration.
     *
     * @param  array<int, string>  $urls  Absolute URLs (https://...).
     * @return array{success: bool, message: string}
     */
    public function purgeUrls(array $urls): array
    {
        if (! $this->isConfigured()) {
            return ['success' => true, 'message' => 'Cloudflare not configured — nothing to purge.'];
        }

        $urls = array_values(array_unique(array_filter($urls)));
        if ($urls === []) {
            return ['success' => true, 'message' => 'No URLs to purge.'];
        }

        try {
            foreach (array_chunk($urls, 30) as $chunk) {
                $response = $this->client()->post(self::API_BASE.'/zones/'.$this->zoneId().'/purge_cache', [
                    'files' => $chunk,
                ]);

                if (! $response->successful() || ! ($response->json('success') ?? false)) {
                    $message = $this->firstErrorMessage($response) ?? "HTTP {$response->status()}";
                    Log::warning('Cloudflare purge failed', ['urls' => $chunk, 'error' => $message]);

                    return ['success' => false, 'message' => $message];
                }
            }

            return ['success' => true, 'message' => count($urls).' URL(s) purged.'];
        } catch (\Throwable $e) {
            Log::warning('Cloudflare purge failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array{success: bool, message: string} */
    public function purgeEverything(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Cloudflare is not configured — set the Zone ID and API Token first.'];
        }

        try {
            $response = $this->client()->post(self::API_BASE.'/zones/'.$this->zoneId().'/purge_cache', [
                'purge_everything' => true,
            ]);

            if (! $response->successful() || ! ($response->json('success') ?? false)) {
                return ['success' => false, 'message' => $this->firstErrorMessage($response) ?? "HTTP {$response->status()}"];
            }

            return ['success' => true, 'message' => 'Entire Cloudflare cache purged.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function client()
    {
        // retry()'s $throw parameter defaults to true — without throw:
        // false, a non-2xx response (e.g. "invalid token") throws a generic
        // RequestException after exhausting retries instead of returning
        // normally, discarding Cloudflare's own {"errors":[...]} body that
        // firstErrorMessage() needs to build a useful message.
        return Http::withToken((string) settings('performance.cloudflare_api_token'))
            ->timeout(15)
            ->retry(2, 500, throw: false);
    }

    private function zoneId(): string
    {
        return (string) settings('performance.cloudflare_zone_id');
    }

    private function firstErrorMessage($response): ?string
    {
        $errors = $response->json('errors');

        if (is_array($errors) && isset($errors[0]['message'])) {
            return $errors[0]['message'];
        }

        return null;
    }
}
