<?php

namespace App\Filament\Support;

/**
 * Dashboard drilldown contract (github-issues.md Issue 3).
 *
 * A widget implementing this exposes a click-through target — typically a
 * resource list pre-filtered to match the widget's data scope, built with
 * AdminUi::drilldownUrl(). Rendered by chart-with-drilldown.blade.php and
 * available to any custom widget view.
 */
interface DrilldownContract
{
    public function getDrilldownUrl(): ?string;
}
