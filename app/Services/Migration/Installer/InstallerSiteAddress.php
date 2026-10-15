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
    /** What the field starts with: the address the owner is using right now. */
    public function prefill(Request $request): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    /**
     * @return string `scheme://host[:port]`, lower case, no trailing slash
     *
     * @throws MigrationException SiteUrlInvalid, or SiteUrlHttpsUnavailable
     */
    public function check(string $input, Request $request): string
    {
        $url = trim($input);
        $parts = parse_url($url);

        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';

        if ($url === '' || ! in_array($scheme, ['http', 'https'], true)
            || ! CanonicalHostPolicy::isBare($host)
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
