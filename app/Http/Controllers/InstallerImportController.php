<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ImportWizardOwner;
use App\Services\Migration\Installer\ImportWizardContext;
use App\Services\Migration\Installer\ImportWizardState;
use App\Services\Migration\Installer\InstallerChoice;
use App\Services\Migration\Installer\InstallerImportReport;
use App\Services\Migration\Installer\InstallerImportRun;
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
        private readonly InstallerChoice $choice,
        private readonly InstallerImportRun $runs,
        private readonly InstallerImportReport $reports,
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

    /**
     * GET /install/import/check — step 5: what was chosen, and the button that starts the checks. Creates NOTHING: a run is
     * created only by the POST below, so a link, a prefetch or a crawler can never start one.
     */
    public function check(Request $request): Response
    {
        $ready = $this->readyToCheck();

        if ($ready instanceof RedirectResponse) {
            return $ready;
        }

        return response()->view('installer.import.check', $this->context->viewData($request) + [
            'currentStep' => 5,
            'open' => $ready['open'],
            'choices' => $ready['choices'],
            'described' => $this->choice->describe($ready['choices']),
        ]);
    }

    /**
     * POST /install/import/check — create the run (the installer's CSRF token and this browser's wizard cookie are both required)
     * and hand the browser to the runner page. The runner's token goes in the address FRAGMENT: it never reaches a server log.
     */
    public function begin(Request $request): RedirectResponse
    {
        $ready = $this->readyToCheck();

        if ($ready instanceof RedirectResponse) {
            return $ready;
        }

        if (! $this->wizard->databaseUnchanged()) {
            return redirect()->route('installer.import.package')->with('import_error', [
                'title' => 'The database settings changed',
                'message' => 'The database settings changed while this import was prepared. Start the import branch again.',
                'fix' => 'Cancel the import branch and begin again.',
                'code' => 'database_changed',
            ]);
        }

        try {
            $run = $this->runs->start($ready['open'], $ready['choices'], (string) $request->ip());
        } catch (MigrationException $e) {
            return redirect()->route('installer.import.package')->with('import_error', $this->text->forException($e, true));
        }

        return redirect()->to('/migrate/run/'.$run['run_id'].'#t='.$run['token']);
    }

    /**
     * GET /install/import/report/{id} — signed, two hours. Counts, setting NAMES and plain sentences; never a value.
     */
    public function report(string $id): Response
    {
        $report = $this->reports->forRun($id);

        if ($report === null) {
            abort(404);
        }

        return response()->view('installer.import.report', [
            'currentStep' => 7,
            'installerSteps' => ImportWizardContext::STEPS,
            'headerLabel' => 'Installer · Import',
            'report' => $report,
        ])->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * The open package and the saved choices, or the page to go back to when either is missing.
     *
     * @return array{open: array{package: string, escrow_id: string, package_id: string, fingerprint: string, summary: array<string, mixed>}, choices: array<string, mixed>}|RedirectResponse
     */
    private function readyToCheck(): array|RedirectResponse
    {
        if (! session('installer.db_configured')) {
            return redirect()->route('installer.database')->with('error', 'Set up the database first, then pick the package.');
        }

        $open = $this->unlock->current();

        if ($open === null) {
            return redirect()->route('installer.import.package')->with('error', 'Open the package first.');
        }

        $choices = $this->wizard->choices();

        if (! $this->runs->choicesComplete($choices)) {
            return redirect()->route('installer.import.choose')->with('error', 'Choose what to bring over first.');
        }

        return ['open' => $open, 'choices' => $choices];
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
