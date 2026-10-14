<?php

namespace App\Services\Migration\Installer;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Http\Request;

/**
 * Keeps a package passphrase from travelling over a connection anyone on the way can read.
 *
 * The installer has no login and may be reached over plain http (a fresh server has no certificate yet). The passphrase
 * protects a full copy of a shop, so the form that takes it is refused unless the request is secure (after the trusted
 * proxy headers were applied), the host being asked for is the machine itself, or the operator has accepted the risk in
 * the environment (OE_INSTALLER_ALLOW_HTTP_PASSPHRASE, never a form field). The command line is the alternative and the
 * page says so.
 */
final class PassphraseTransportGuard
{
    public function allows(Request $request): bool
    {
        if ($request->isSecure() || (bool) config('migration.installer.allow_http_passphrase', false)) {
            return true;
        }

        return self::isLoopbackHost($request->getHost());
    }

    /**
     * @throws MigrationException PassphraseOverHttp
     */
    public function assertAllows(Request $request): void
    {
        if (! $this->allows($request)) {
            throw new MigrationException(MigrationErrorCode::PassphraseOverHttp);
        }
    }

    public static function isLoopbackHost(string $host): bool
    {
        $host = strtolower(trim($host, "[] \t"));

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || $host === '::1') {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && str_starts_with($host, '127.');
    }
}
