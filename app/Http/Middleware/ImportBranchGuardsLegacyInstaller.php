<?php

namespace App\Http\Middleware;

use App\Services\Migration\Installer\ImportWizardState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The classic installer pages (database, settings, admin, mail, run, retry) have no login. While a browser holds the import
 * branch they belong to it: a stranger could otherwise rewrite the shared .env database settings under the owner's run, or
 * start a second run (migrate:fresh) beneath it. With no branch held this changes nothing at all.
 *
 * While the branch is held, the run pages of the classic installer (run, advance, retry) are closed to everybody: an import
 * is run by the runner page, never by the classic install steps.
 */
final class ImportBranchGuardsLegacyInstaller
{
    private const RUN_ROUTES = ['installer.install', 'installer.advance', 'installer.retry'];

    public function __construct(private readonly ImportWizardState $wizard) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->wizard->held()) {
            return $next($request);
        }

        if (! $this->wizard->owns($request->cookie(ImportWizardState::COOKIE))) {
            return ImportWizardOwner::ownedElsewhere();
        }

        if (in_array($request->route()?->getName(), self::RUN_ROUTES, true)) {
            return ImportWizardOwner::ownedElsewhere();
        }

        return $next($request);
    }
}
