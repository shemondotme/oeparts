<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Full-width section header rendered at the start of each dashboard group
 * (Business Overview, Needs Attention, …). Structural only — never in the
 * WidgetPreferenceService registry and never toggleable.
 *
 * $isDiscovered = false keeps Filament's discoverWidgets() from auto-adding it
 * to the dashboard; Dashboard::getWidgets() injects configured instances
 * (one per non-empty group) via GroupHeaderWidget::make(['label' => ...]).
 *
 * Being full-width, it forces a new grid row, so every group starts on its own
 * row and half-width widgets never pair across a group boundary.
 */
class GroupHeaderWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.group-header';

    public string $label = '';
}
