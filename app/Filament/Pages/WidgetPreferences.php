<?php

namespace App\Filament\Pages;

use App\Services\WidgetPreferenceService;
use Filament\Pages\Page;
use Livewire\Attributes\On;
use Filament\Notifications\Notification;

class WidgetPreferences extends Page
{
    protected string $view = 'filament.pages.widget-preferences';

    protected static ?string $title = 'Customize Dashboard';

    protected static ?string $slug = 'preferences/widgets';

    protected static bool $shouldRegisterNavigation = false;

    public array $visibility = [];

    public array $groupedWidgets = [];

    public function getSubheading(): ?string
    {
        return 'Select which widgets appear on your dashboard. Changes are saved automatically.';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('backToDashboard')
                ->label('Back to Dashboard')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(Dashboard::getUrl()),
            \Filament\Actions\Action::make('resetToDefaults')
                ->label('Reset to Defaults')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Reset dashboard preferences')
                ->modalDescription('Your widget choices will be replaced by the defaults for your role.')
                ->action('resetToDefaults'),
        ];
    }

    public function mount(): void
    {
        $service = app(WidgetPreferenceService::class);
        $admin = auth('admin')->user();

        if (! $admin) {
            abort(403, 'Unauthorized');
        }

        // Initialize visibility state from preferences
        $this->visibility = $this->loadVisibility($service);

        // Build grouped widgets list
        $this->groupedWidgets = $this->buildGroupedWidgets($service, $admin);
    }

    #[On('toggle-widget')]
    public function toggleWidget(string $widgetId): void
    {
        $service = app(WidgetPreferenceService::class);

        // Only real, role-visible, toggleable widgets — otherwise a crafted
        // Livewire call could persist junk keys into dashboard_preferences.
        $config = WidgetPreferenceService::WIDGETS[$widgetId] ?? null;
        $role = auth('admin')->user()?->roles()->first()?->name ?? 'support';

        if (
            $config === null
            || in_array($widgetId, WidgetPreferenceService::ALWAYS_ON, true)
            || ! in_array($role, $config['roles'], true)
        ) {
            return;
        }

        $currentValue = $this->visibility[$widgetId] ?? false;
        $newValue = ! $currentValue;

        $this->visibility[$widgetId] = $newValue;
        $service->saveVisibility($widgetId, $newValue);

        Notification::make()
            ->title($newValue ? 'Widget shown' : 'Widget hidden')
            ->success()
            ->send();
    }

    public function resetToDefaults(): void
    {
        $service = app(WidgetPreferenceService::class);
        $service->resetVisibility();

        // Reload visibility
        $this->visibility = $this->loadVisibility($service);

        Notification::make()
            ->title('Reset to defaults')
            ->body('Dashboard preferences have been reset to your role defaults.')
            ->success()
            ->send();
    }

    private function loadVisibility(WidgetPreferenceService $service): array
    {
        $visibility = [];

        // Only include widgets that are NOT always-on
        foreach (WidgetPreferenceService::WIDGETS as $widgetId => $config) {
            if (in_array($widgetId, WidgetPreferenceService::ALWAYS_ON, true)) {
                continue;
            }

            $visibility[$widgetId] = $service->getVisibility($widgetId);
        }

        return $visibility;
    }

    private function buildGroupedWidgets(WidgetPreferenceService $service, $admin): array
    {
        $grouped = [];
        $admin_role = $admin->roles()->first()?->name ?? 'support';

        // Initialize all groups
        foreach (WidgetPreferenceService::GROUP_SLUGS as $slug => $label) {
            $grouped[$slug] = [
                'label' => $label,
                'widgets' => [],
            ];
        }

        // Populate widgets in their groups
        foreach (WidgetPreferenceService::WIDGETS as $widgetId => $config) {
            // Skip always-on widgets
            if (in_array($widgetId, WidgetPreferenceService::ALWAYS_ON, true)) {
                continue;
            }

            // Skip widgets this role can't access
            if (! in_array($admin_role, $config['roles'], true)) {
                continue;
            }

            $groupSlug = $config['group'] ?? 'business-overview';
            $grouped[$groupSlug]['widgets'][] = [
                'id' => $widgetId,
                'label' => $config['label'],
                'description' => $config['description'],
                'visible' => $this->visibility[$widgetId] ?? false,
            ];
        }

        // Remove empty groups
        return array_filter($grouped, fn($group) => ! empty($group['widgets']));
    }
}
