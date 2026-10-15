<?php

namespace App\Http\Controllers;

use App\Services\Migration\Installer\ImportWizardContext;
use App\Services\Migration\Installer\ImportWizardState;
use App\Services\Migration\Installer\InstallerChoice;
use App\Services\Migration\Installer\InstallerPackageUnlock;
use App\Services\Migration\Installer\InstallerSiteAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step 4 of the installer's import branch: what to bring, live or test, and the address of the new site.
 *
 * Behind ImportWizardOwner like every page of the branch, and it needs a package that is open (step 3). The choices are written
 * to the wizard file; they hold no secret. Nothing is started here: the run is created by step 5, with a POST.
 */
class InstallerImportChoiceController extends Controller
{
    public function __construct(
        private readonly ImportWizardState $wizard,
        private readonly ImportWizardContext $context,
        private readonly InstallerPackageUnlock $unlock,
        private readonly InstallerChoice $choice,
        private readonly InstallerSiteAddress $address,
    ) {}

    /** GET /install/import/choose */
    public function show(Request $request): Response
    {
        $open = $this->unlock->current();

        if ($open === null) {
            return $this->needsPackage();
        }

        $held = $this->heldGroups($open);
        $saved = $this->wizard->choices();
        $presets = $this->choice->presets($held);

        // Start from what the package was made as, so "bring what I exported" is one click; the live/test question never has a default.
        $ownPreset = $open['summary']['preset'] ?? null;
        $selection = (string) old('selection', $saved['selection'] ?? (is_string($ownPreset) && in_array($ownPreset, array_column($presets, 'key'), true) ? 'preset:'.$ownPreset : ''));

        return response()->view('installer.import.choose', $this->context->viewData($request) + [
            'currentStep' => 4,
            'open' => $open,
            'presets' => $presets,
            'groups' => $this->choice->groups($held),
            'selection' => $selection,
            'ticked' => (array) old('groups', $saved['groups'] ?? []),
            'launch' => (string) old('launch', $saved['launch'] ?? ''),
            'siteUrl' => (string) old('site_url', $saved['url'] ?? $this->address->prefill($request)),
            'saved' => $saved !== [] && session('choices_saved') === true,
            'anonymised' => (bool) ($open['summary']['anonymised'] ?? false),
        ])->header('Cache-Control', 'no-store, private');
    }

    /** POST /install/import/choose */
    public function save(Request $request): RedirectResponse
    {
        $open = $this->unlock->current();

        if ($open === null) {
            return redirect()->route('installer.import.package')->with('error', 'Open the package first.');
        }

        $result = $this->choice->check($this->heldGroups($open), $request->only(['selection', 'groups', 'launch', 'site_url']), $request);

        if ($result['choices'] === null) {
            return redirect()->route('installer.import.choose')
                ->withErrors($result['errors'])
                ->withInput($request->only(['selection', 'groups', 'launch', 'site_url']));
        }

        $this->wizard->change(['choices' => $result['choices']]);

        return redirect()->route('installer.import.choose')->with('choices_saved', true);
    }

    private function needsPackage(): RedirectResponse
    {
        return redirect()->route('installer.import.package')->with('error', 'Open the package first, then choose what to bring.');
    }

    /**
     * @param  array{summary: array<string, mixed>}  $open
     * @return array<int, string>
     */
    private function heldGroups(array $open): array
    {
        return array_values(array_filter((array) ($open['summary']['groups'] ?? []), 'is_string'));
    }
}
