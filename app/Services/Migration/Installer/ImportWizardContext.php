<?php

namespace App\Services\Migration\Installer;

use Illuminate\Http\Request;

/**
 * Is this browser inside the installer's import branch, and what does the page chrome then look like.
 *
 * The step bar of the branch is `Requirements | Database | Package | Choose | Check | Import | Done`; the first two steps are
 * the installer's own pages, so they ask this class which bar to draw.
 */
final class ImportWizardContext
{
    /** @var array<int, string> */
    public const STEPS = [
        1 => 'Requirements',
        2 => 'Database',
        3 => 'Package',
        4 => 'Choose',
        5 => 'Check',
        6 => 'Import',
        7 => 'Done',
    ];

    public function __construct(private readonly ImportWizardState $wizard) {}

    public function active(Request $request): bool
    {
        return $this->wizard->owns($request->cookie(ImportWizardState::COOKIE));
    }

    /** @return array<string, mixed> the extra view data for layouts.installer; empty outside the branch */
    public function viewData(Request $request): array
    {
        if (! $this->active($request)) {
            return [];
        }

        return ['installerSteps' => self::STEPS, 'headerLabel' => 'Installer · Import', 'importMode' => true];
    }
}
