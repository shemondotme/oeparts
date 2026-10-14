<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ImportWizardOwner;
use App\Services\Migration\Installer\ImportWizardContext;
use App\Services\Migration\Installer\ImportWizardState;
use App\Services\Migration\Installer\InstallerInbox;
use App\Services\Migration\Installer\InstallerPackageUnlock;
use App\Services\Migration\Installer\PassphraseTransportGuard;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Ux\MigrationText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The installer's "import an existing site" branch. The existing InstallerController is already about 500 lines, so the
 * branch has its own controller; the first two steps (requirements, database) stay with the old one and only learn which
 * step bar to draw and where to go next.
 *
 * Every page except the one that starts the branch sits behind ImportWizardOwner (one browser owns the branch). Nothing
 * here creates a run from a GET.
 */
class InstallerImportController extends Controller
{
    public function __construct(
        private readonly ImportWizardState $wizard,
        private readonly ImportWizardContext $context,
        private readonly InstallerInbox $inbox,
        private readonly InstallerPackageUnlock $unlock,
        private readonly PassphraseTransportGuard $transport,
        private readonly MigrationText $text,
    ) {}

    /** GET /install/import — the page that explains the branch and starts it. Creates nothing. */
    public function mode(Request $request): Response
    {
        if ($this->context->active($request)) {
            return redirect($this->resumeUrl());
        }

        if ($this->wizard->exists() && ! $this->wizard->isIdle()) {
            return ImportWizardOwner::ownedElsewhere();
        }

        return response()->view('installer.import.mode', [
            'currentStep' => 1,
            'inboxPath' => $this->inbox->displayPath(),
            'packagesWaiting' => $this->inbox->stableCount(),
        ])->header('Cache-Control', 'no-store, private');
    }

    /** POST /install/import — this browser takes the branch (CSRF-protected like every installer form). */
    public function start(Request $request): Response
    {
        if ($this->context->active($request)) {
            return redirect($this->resumeUrl());
        }

        $nonce = $this->wizard->claim((string) $request->ip());

        if ($nonce === null) {
            return ImportWizardOwner::ownedElsewhere();
        }

        return redirect()->route('installer.database')->withCookie($this->cookie($request, $nonce));
    }

    /** POST /install/import/cancel — the owner chooses a normal install after all. */
    public function cancel(Request $request): RedirectResponse
    {
        // The keys of an open package go with the branch.
        $this->unlock->release();
        $this->wizard->reset();

        // From the first step the owner may also choose "Install a new site", which goes straight on to the database step.
        $next = $request->input('next') === 'database' ? 'installer.database' : 'installer.index';

        return redirect()->route($next)->withCookie(Cookie::forget(ImportWizardState::COOKIE, '/install'));
    }

    /** GET /install/import/package — step 3: the packages waiting in the inbox, or the package that is open. */
    public function package(Request $request): Response
    {
        if (! session('installer.db_configured')) {
            return redirect()->route('installer.database')->with('error', 'Set up the database first, then pick the package.');
        }

        $canType = $this->transport->allows($request);

        return response()->view('installer.import.package', $this->context->viewData($request) + [
            'currentStep' => 3,
            'open' => $this->unlock->current(),
            'files' => $this->inbox->listWithProblems(),
            'listing' => $this->inbox->listingEnabled(),
            'inboxPath' => $this->inbox->displayPath(),
            'ipChanged' => $this->ipChanged($request),
            'canTypePassphrase' => $canType,
            'insecureNotice' => $this->text->get('error.passphrase_over_http.message', [], true),
            'selected' => (string) old('package', ''),
            'importError' => session('import_error'),
        ]);
    }

    /**
     * POST /install/import/package — open the chosen package with the typed passphrase. The passphrase is used for this one
     * call; it is never stored, flashed back into the form or logged. A refusal is shown in plain words on the same page.
     */
    public function open(Request $request): RedirectResponse
    {
        try {
            $this->unlock->open($request, (string) $request->input('package', ''), (string) $request->input('passphrase', ''));
        } catch (MigrationException $e) {
            // Only the file name is carried back into the form, never the passphrase.
            return redirect()->route('installer.import.package')
                ->with('import_error', $this->text->forException($e, true))
                ->withInput($request->only('package'));
        }

        return redirect()->route('installer.import.package');
    }

    /** POST /install/import/package/forget — close the open package and pick another. */
    public function forget(): RedirectResponse
    {
        $this->unlock->forget();

        return redirect()->route('installer.import.package');
    }

    private function resumeUrl(): string
    {
        return session('installer.db_configured') ? route('installer.import.package') : route('installer.database');
    }

    /** A different address than the one that started the branch is a warning, not a gate: phones and proxies change address. */
    private function ipChanged(Request $request): bool
    {
        $first = $this->wizard->ip();

        return $first !== null && $first !== (string) $request->ip();
    }

    private function cookie(Request $request, string $nonce): \Symfony\Component\HttpFoundation\Cookie
    {
        // HttpOnly, SameSite=Strict, path /install, Secure when this request came over https. 24 hours: a restarted browser
        // must not lose its own branch. Not encrypted (listed in EncryptCookies' exceptions): the value is a random
        // 256-bit nonce, the server keeps only its hash, and it must keep working if APP_KEY changes during an import.
        return Cookie::make(
            ImportWizardState::COOKIE,
            $nonce,
            1440,
            '/install',
            null,
            $request->isSecure(),
            true,
            false,
            'strict',
        );
    }
}
