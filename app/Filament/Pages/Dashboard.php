<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\GroupHeaderWidget;
use App\Services\WidgetPreferenceService;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -2;

    public string $period = '30';

    /**
     * Dashboard-header "Customize" button — the single, discoverable entry
     * point for managing which widgets appear (moved here from the hidden
     * user-menu item). WidgetPreferences enforces its own auth in mount().
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('customizeDashboard')
                ->label('Customize')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->outlined()
                ->url(WidgetPreferences::getUrl()),
        ];
    }

    public function mount(): void
    {
        $admin = auth('admin')->user();

        if ($admin) {
            $this->period = app(WidgetPreferenceService::class)->getPeriod();
        }
    }

    #[\Livewire\Attributes\Renderless]
    public function setPeriod(string $period): void
    {
        $this->period = $period;
        app(WidgetPreferenceService::class)->savePeriod($period);
        $this->dispatch('period-changed', period: $period);
    }

    public function getWidgets(): array
    {
        $widgets = parent::getWidgets();
        $service = app(WidgetPreferenceService::class);
        $admin = auth('admin')->user();

        $filtered = array_filter($widgets, function ($widget) use ($service, $admin) {
            $class = $widget instanceof WidgetConfiguration ? $widget->widget : (is_string($widget) ? $widget : null);

            if (! $class) {
                return false;
            }

            // Check if admin's role can access this widget
            $allowedRoles = WidgetPreferenceService::rolesFor($class);
            $adminRole = $admin?->roles()->first()?->name ?? 'support';
            if (! in_array($adminRole, $allowedRoles, true)) {
                return false;
            }

            // Get widget ID and check visibility preference
            $widgetId = $service->getWidgetId($class);
            if (! $widgetId) {
                return true;
            }

            return $service->getVisibility($widgetId);
        });

        // Order the grid by the registry's default_sort (single source of truth
        // for layout order). Filament preserves getWidgets() array order — it
        // does NOT re-sort by each widget's legacy $sort property — so the
        // dashboard renders groups in sequence with widgets ordered within them.
        usort($filtered, function ($a, $b): int {
            $ca = $a instanceof WidgetConfiguration ? $a->widget : $a;
            $cb = $b instanceof WidgetConfiguration ? $b->widget : $b;

            return WidgetPreferenceService::sortFor($ca) <=> WidgetPreferenceService::sortFor($cb);
        });

        return $this->withGroupHeaders(array_values($filtered), $service);
    }

    /**
     * Inject a full-width GroupHeaderWidget before the first visible widget of
     * each group, so every group starts on its own row with a section title
     * (matching the planned grouped layout). Always-on widgets (dashboard_header,
     * health_strip) sit above the first group and get no header. Empty groups
     * get no header since the header is only emitted when a group's first
     * visible widget is reached.
     *
     * @param  array<int, string|WidgetConfiguration>  $widgets
     * @return array<int, string|WidgetConfiguration>
     */
    protected function withGroupHeaders(array $widgets, WidgetPreferenceService $service): array
    {
        $result = [];
        $lastGroup = null;

        foreach ($widgets as $widget) {
            $class = $widget instanceof WidgetConfiguration ? $widget->widget : $widget;
            $id = is_string($class) ? $service->getWidgetId($class) : null;

            // Always-on widgets render above the groups and never carry a header.
            if ($id !== null && ! in_array($id, WidgetPreferenceService::ALWAYS_ON, true)) {
                $group = WidgetPreferenceService::groupFor($class);

                if ($group !== null && $group !== $lastGroup) {
                    $label = WidgetPreferenceService::GROUP_SLUGS[$group] ?? '';
                    $result[] = GroupHeaderWidget::make(['label' => $label]);
                    $lastGroup = $group;
                }
            }

            $result[] = $widget;
        }

        return $result;
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 2,
        ];
    }
}
