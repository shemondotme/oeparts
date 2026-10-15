<?php

namespace App\Services\Migration\Installer;

use App\Services\Instance\CanonicalHostPolicy;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Http\Request;

/**
 * The address the imported site will answer on, as the installer takes it.
 *
 * The same rules the engine applies when the run starts (ImportTarget): http or https, a bare host with an optional port, no
 * folder, no user name, no query. The installer adds the one rule only it can check: an `https://` address is refused unless
 * THIS request itself arrived over https. EnforceCanonicalHost computes whether the site is "https configured" from APP_URL, so
 * writing an https address from a page that was opened over http would send every later visit to a port nothing answers on, and
 * the owner would be locked out of the site they just imported.
 */
final class InstallerSiteAddress
{
    /**
     * What the field starts with: the address the owner is using right now, but only when it is valid for the chosen mode. A server
     * reached by its number (203.0.113.7) prefills only for a test copy; for a live site, or before the question is answered, it is empty.
     */
    public function prefill(Request $request, ?string $launch = null): string
    {
        if (CanonicalHostPolicy::isIpv4($request->getHost()) && $launch !== CanonicalHostPolicy::STAGING) {
            return '';
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    /** Is this address a literal IP (so that only a test copy may use it)? */
    public function isIpAddress(string $input): bool
    {
        $parts = parse_url(trim($input));

        return is_array($parts) && CanonicalHostPolicy::isIpv4(strtolower((string) ($parts['host'] ?? '')));
    }

    /**
     * @return string `scheme://host[:port]`, lower case, no trailing slash
     *
     * @throws MigrationException SiteUrlInvalid, or SiteUrlHttpsUnavailable
     */
    public function check(string $input, Request $request, ?string $launch = null): string
    {
        $url = trim($input);
        $parts = parse_url($url);

        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';

        if ($url === '' || ! in_array($scheme, ['http', 'https'], true)
            || ! (CanonicalHostPolicy::isBare($host) || ($launch === CanonicalHostPolicy::STAGING && CanonicalHostPolicy::isIpv4($host)))
            || ! in_array($path, ['', '/'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new MigrationException(MigrationErrorCode::SiteUrlInvalid);
        }

        if ($scheme === 'https' && ! $request->isSecure()) {
            throw new MigrationException(MigrationErrorCode::SiteUrlHttpsUnavailable);
        }

        return $scheme.'://'.$host.(isset($parts['port']) ? ':'.(int) $parts['port'] : '');
    }
}
