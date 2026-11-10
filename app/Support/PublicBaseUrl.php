<?php

namespace App\Support;

use App\Services\SettingsService;

/**
 * The one answer to "what public address does this site live at?" for code
 * that builds ABSOLUTE URLs outside a normal web request — sitemap
 * generation, IndexNow pushes fired from queue workers / importers, etc.
 *
 * In the CLI (scheduler, queue worker, artisan) url()/route()/asset() fall
 * back to config('app.url'), which on a misconfigured server is still
 * http://localhost — and whatever is built then gets baked into a static
 * file or sent to a third party. This picks the real address instead:
 * the admin-saved site address, then the SEO canonical host, then APP_URL,
 * skipping anything that points at a local/dev host.
 */
final class PublicBaseUrl
{
    /**
     * @throws \RuntimeException on a production instance with no usable public address
     */
    public static function resolve(SettingsService $settings): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $candidates = [];

        $siteUrl = trim((string) $settings->get('general.site_url', ''));
        if ($siteUrl !== '') {
            $candidates[] = rtrim($siteUrl, '/');
        }

        $canonicalHost = trim((string) $settings->get('seo.canonical_host', ''));
        if ($canonicalHost !== '') {
            $host = rtrim((string) preg_replace('#^https?://#i', '', $canonicalHost), '/');
            $scheme = str_starts_with($appUrl, 'http://') && ! app()->environment('production') ? 'http' : 'https';
            $candidates[] = $scheme.'://'.$host;
        }

        $candidates[] = $appUrl;

        foreach ($candidates as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_URL) && ! self::isLocalHost($candidate)) {
                return $candidate;
            }
        }

        if (app()->environment('production')) {
            throw new \RuntimeException(
                'No public site address is configured (APP_URL / General site address / SEO canonical host all point at a local address). Set APP_URL in .env to the live https:// domain.'
            );
        }

        return $appUrl;
    }

    /**
     * Re-roots an absolute URL onto the public address (keeps path and
     * query). Used for URLs that were built while APP_URL was wrong, so
     * they never leave the system with a localhost host. Relative or
     * already-public URLs are returned unchanged.
     */
    public static function rewrite(string $url, SettingsService $settings): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host']) || ! self::isLocalHost($url)) {
            return $url;
        }

        try {
            $base = self::resolve($settings);
        } catch (\RuntimeException) {
            return $url;
        }

        if (self::isLocalHost($base)) {
            return $url;
        }

        return $base.($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    public static function isLocalHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === ''
            || in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)
            || preg_match('/\.(test|local|localhost|invalid|example)$/', $host) === 1;
    }
}
