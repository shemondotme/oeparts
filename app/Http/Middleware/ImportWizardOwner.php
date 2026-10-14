<?php

namespace App\Http\Middleware;

use App\Services\Migration\Installer\ImportWizardState;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\Ux\MigrationText;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On every page of the installer's import branch except the one that starts it: this browser must be the one that started it.
 *
 * `/install` has no login, so without this anyone who reaches it before the owner has finished could read or change the
 * choices or start a run. A stranger sees one plain page with no data and no button that changes anything. When nobody holds
 * the branch (it was never started, was reset on the server, or its owner went idle) the visitor is sent to the page that
 * starts it instead, because there is nothing to protect.
 */
final class ImportWizardOwner
{
    public function __construct(
        private readonly ImportWizardState $wizard,
        private readonly MigrationText $text,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->wizard->owns($request->cookie(ImportWizardState::COOKIE))) {
            if ($this->wizard->isIdle()) {
                return redirect()->route('installer.import.mode');
            }

            return self::ownedElsewhere($this->text);
        }

        $this->wizard->touch();

        $response = $next($request);

        // A page of the branch names the files on this server and the choices of its owner: never cached anywhere.
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /** The page a second browser gets. No data from the branch, no form, no way to change anything. */
    public static function ownedElsewhere(?MigrationText $text = null): Response
    {
        $text ??= app(MigrationText::class);
        $error = $text->error(MigrationErrorCode::WizardOwnedElsewhere, [], true);

        return response()
            ->view('installer.import.owned-elsewhere', ['error' => $error], 403)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
