<?php

namespace App\Filament\Pages;

use App\Services\DashboardLayoutService;
use App\Services\WidgetPreferenceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public string $period = '30';

    public bool $editMode = false;

    public ?int $activeDashboardId = null;

    /** Holds selected widget IDs while the Manage Widgets modal is open. */
    public array $widgetSelections = [];

    /** Request-scoped cache for getCanvasItems() to avoid repeated layout queries. */
    private ?array $cachedCanvasItems = null;

    public function mount(): void
    {
        $admin = auth('admin')->user();

        if ($admin) {
            $prefs = app(WidgetPreferenceService::class);
            $this->period = $prefs->getPeriod();
            $this->activeDashboardId = app(DashboardLayoutService::class)
                ->ensureDefaultDashboard($admin)
                ->id;
        }
    }

    #[\Livewire\Attributes\Renderless]
    public function setPeriod(string $period): void
    {
        $this->period = $period;
        app(WidgetPreferenceService::class)->savePeriod($period);
        $this->dispatch('period-changed', period: $period);
    }

    public function toggleEditMode(): void
    {
        $this->editMode = ! $this->editMode;
        $this->dispatch('dashboard-edit-mode', enabled: $this->editMode);
    }

    /** Called from dashboard-canvas.js with gridstack's serialized layout. */
    public function saveLayout(array $items): void
    {
        $admin = auth('admin')->user();

        if (! $admin || ! $this->activeDashboardId) {
            return;
        }

        app(DashboardLayoutService::class)->saveLayout($admin, $this->activeDashboardId, $items);
    }

    public function switchDashboard(int $dashboardId): void
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return;
        }

        $this->activeDashboardId = app(DashboardLayoutService::class)
            ->switchTo($admin, $dashboardId)
            ->id;
        $this->editMode = false;
    }

    /** @return list<array{id:string,class:string,x:int,y:int,w:int,h:int}> */
    public function getCanvasItems(): array
    {
        if ($this->cachedCanvasItems !== null) {
            return $this->cachedCanvasItems;
        }

        $admin = auth('admin')->user();

        if (! $admin || ! $this->activeDashboardId) {
            return $this->cachedCanvasItems = [];
        }

        $service = app(DashboardLayoutService::class);

        return $this->cachedCanvasItems = $service->canvasItems($admin, $service->activeDashboard($admin));
    }

    /** @return list<array{id:int,name:string}> */
    public function getDashboardList(): array
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return [];
        }

        return app(DashboardLayoutService::class)
            ->listFor($admin)
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])
            ->all();
    }

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 2,
        ];
    }

    /**
     * Kept for Filament internals and tests: active-canvas widget classes in
     * layout order, restricted to what the role may view.
     */
    public function getWidgets(): array
    {
        $items = $this->getCanvasItems();

        if ($items !== []) {
            return array_column($items, 'class');
        }

        // No admin context (e.g. unit usage) — fall back to legacy behavior.
        $widgets = parent::getWidgets();
        $service = app(WidgetPreferenceService::class);

        $filtered = array_filter($widgets, function ($widget) use ($service) {
            $class = $widget instanceof WidgetConfiguration ? $widget->widget : (is_string($widget) ? $widget : null);
            return $class && $service->isEnabled($class);
        });

        return array_values($filtered);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('switchDashboard')
                ->label(function (): string {
                    $admin = auth('admin')->user();

                    if (! $admin || ! $this->activeDashboardId) {
                        return 'Dashboard';
                    }

                    return app(DashboardLayoutService::class)->activeDashboard($admin)->name;
                })
                ->icon('heroicon-o-squares-plus')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\Select::make('dashboard_id')
                        ->label('Dashboard')
                        ->options(fn () => collect($this->getDashboardList())->pluck('name', 'id'))
                        ->default(fn () => $this->activeDashboardId)
                        ->required(),
                ])
                ->modalHeading('Switch dashboard')
                ->modalWidth(Width::Small)
                ->action(fn (array $data) => $this->switchDashboard((int) $data['dashboard_id'])),
            Action::make('newDashboard')
                ->label('New')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Dashboard name')
                        ->required()
                        ->maxLength(100),
                ])
                ->modalHeading('Create dashboard')
                ->modalWidth(Width::Small)
                ->action(function (array $data): void {
                    $admin = auth('admin')->user();

                    if (! $admin) {
                        return;
                    }

                    $dashboard = app(DashboardLayoutService::class)->create($admin, $data['name']);
                    $this->activeDashboardId = $dashboard->id;

                    Notification::make()->title('Dashboard created')->success()->send();
                }),
            Action::make('renameDashboard')
                ->label('Rename')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->form([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Dashboard name')
                        ->required()
                        ->maxLength(100),
                ])
                ->fillForm(function (): array {
                    $admin = auth('admin')->user();

                    return [
                        'name' => $admin && $this->activeDashboardId
                            ? app(DashboardLayoutService::class)->activeDashboard($admin)->name
                            : '',
                    ];
                })
                ->modalHeading('Rename dashboard')
                ->modalWidth(Width::Small)
                ->action(function (array $data): void {
                    $admin = auth('admin')->user();

                    if ($admin && $this->activeDashboardId) {
                        app(DashboardLayoutService::class)->rename($admin, $this->activeDashboardId, $data['name']);
                    }
                }),
            Action::make('deleteDashboard')
                ->label('Delete')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete this dashboard?')
                ->modalDescription('Widgets are not deleted — only this layout. Your last dashboard cannot be deleted.')
                ->action(function (): void {
                    $admin = auth('admin')->user();

                    if (! $admin || ! $this->activeDashboardId) {
                        return;
                    }

                    $deleted = app(DashboardLayoutService::class)->delete($admin, $this->activeDashboardId);

                    if (! $deleted) {
                        Notification::make()->title('Cannot delete your last dashboard')->warning()->send();

                        return;
                    }

                    $this->activeDashboardId = app(DashboardLayoutService::class)->activeDashboard($admin)->id;

                    Notification::make()->title('Dashboard deleted')->success()->send();
                }),
            Action::make('editLayout')
                ->label(fn (): string => $this->editMode ? 'Done' : 'Edit Layout')
                ->icon(fn (): string => $this->editMode ? 'heroicon-o-check' : 'heroicon-o-arrows-pointing-out')
                ->color(fn (): string => $this->editMode ? 'success' : 'gray')
                ->action('toggleEditMode'),
            Action::make('resetLayout')
                ->label('Reset to Defaults')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset layout to defaults?')
                ->modalDescription('This will reset your current dashboard widgets to their default positions and sizes. Your custom arrangements will be lost.')
                ->action(function (): void {
                    $admin = auth('admin')->user();

                    if (! $admin || ! $this->activeDashboardId) {
                        return;
                    }

                    $service = app(DashboardLayoutService::class);
                    $defaultWidgetIds = app(WidgetPreferenceService::class)->roleDefaultWidgetIds($admin);
                    $defaultLayout = $service->packLayout($defaultWidgetIds);

                    \App\Models\AdminDashboard::where('admin_id', $admin->id)
                        ->where('id', $this->activeDashboardId)
                        ->update(['layout' => $defaultLayout]);

                    Notification::make()->title('Dashboard layout reset to defaults')->success()->send();

                    $this->redirect(route('filament.admin.pages.dashboard'));
                }),
            Action::make('manageWidgets')
                ->label('Manage Widgets')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->modalHeading('Dashboard Widgets')
                ->modalDescription('Select the widgets you want to display on your dashboard.')
                ->modalWidth(Width::TwoExtraLarge)
                ->modalSubmitActionLabel('Save')
                ->mountUsing(function () {
                    $this->widgetSelections = array_column($this->getCanvasItems(), 'id');
                })
                ->modalContent(function () {
                    $admin = auth('admin')->user();
                    $onCanvas = array_column($this->getCanvasItems(), 'id');

                    $widgets = [];
                    foreach (WidgetPreferenceService::WIDGETS as $id => $config) {
                        if (! $admin || ! $admin->hasAnyRole($config['roles'])) {
                            continue;
                        }
                        $widgets[] = [
                            'id'          => $id,
                            'label'       => $config['label'],
                            'enabled'     => in_array($id, $onCanvas, true),
                            'description' => $this->getWidgetDescription($id),
                            'icon'        => $this->getWidgetIconSvg($id),
                        ];
                    }

                    // Sort: enabled first, then alphabetical
                    usort($widgets, fn ($a, $b) =>
                        ($b['enabled'] <=> $a['enabled']) ?: strcmp($a['label'], $b['label'])
                    );

                    return view('filament.modals.widget-management', [
                        'widgets' => $widgets,
                    ]);
                })
                ->action(function () {
                    $admin    = auth('admin')->user();
                    $service  = app(WidgetPreferenceService::class);
                    $knownIds = $service->widgetIds();

                    $enabledIds = array_values(array_filter(
                        $this->widgetSelections,
                        fn ($id) => in_array($id, $knownIds, true)
                    ));

                    $prefs = [];
                    $sort  = 1;
                    foreach ($knownIds as $id) {
                        $prefs[$id] = [
                            'hidden' => ! in_array($id, $enabledIds, true),
                            'sort'   => $sort++,
                        ];
                    }

                    $service->savePreferences($prefs);

                    if ($admin && $this->activeDashboardId) {
                        app(DashboardLayoutService::class)
                            ->setWidgets($admin, $this->activeDashboardId, $enabledIds);
                    }

                    Notification::make()
                        ->title('Widget preferences updated')
                        ->success()
                        ->send();

                    $this->redirect(route('filament.admin.pages.dashboard'));
                }),
        ];
    }

    protected function getWidgetIconSvg(string $id): string
    {
        $svgStyle = 'style="color: var(--color-warning-500);"';

        $svgs = [
            'dashboard_header' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
            'kpi_stats' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0h.5m-1.5 0h-8.5m.5 0h.5" /></svg>',
            'revenue_chart' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" /></svg>',
            'activity_overview' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'recent_orders' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>',
            'quick_actions' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>',
            'top_searches' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>',
            'failed_searches' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>',
            'alerts' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5" /></svg>',
            'health_strip' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" /></svg>',
            'manufacturer_revenue' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72M6.75 18h3.5a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75h-3.5a.75.75 0 00-.75.75v3.75c0 .414.336.75.75.75z" /></svg>',
            'customer_growth' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.005 9.005 0 00-6-2.673 9.005 9.005 0 00-6 2.673m12 0a9 9 0 11-12 0m12 0c0-3.517-2.179-6.524-5.22-7.84a6.001 6.001 0 00-1.56-1.07M9 7.25a3 3 0 116 0 3 3 0 01-6 0z" /></svg>',
            'checkout_dropoff' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" /></svg>',
            'sales_by_country' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-.778.099-1.533.284-2.253" /></svg>',
            'order_status_distribution' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" /></svg>',
            'payment_method_split' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>',
            'recent_activity' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.03 0 1.9.693 2.166 1.638m-7.377 2.24A9.019 9.019 0 0112 15c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-.778.099-1.533.284-2.253" /></svg>',
            'stock_alert' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.874 1.948 3.874h14.71c1.73 0 2.813-2.374 1.948-3.874L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>',
            'abandoned_carts' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>',
            'coupon_usage' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-6.75-1.5a7.5 7.5 0 1115 0 7.5 7.5 0 01-15 0ZM3.375 6A2.625 2.625 0 016 3.375h4.5A2.625 2.625 0 0113.125 6v13.5a2.625 2.625 0 01-2.625 2.625H6A2.625 2.625 0 013.375 19.5V6Z" /></svg>',
            'parts_inquiry' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>',
            'latest_customers' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>',
            'manufacturing_stats' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l2.5-2.5m-2.5 2.5a3.69 3.69 0 01-3.68-3.68 3.69 3.69 0 013.68-3.68 3.69 3.69 0 013.68 3.68m-3.68 3.68L9.05 18.1m-4.93-4.93L2.46 14.6m7.74-5.48l2.82-2.82M10.5 5.25h3m-3 18H3m15-18h3m-3 18h-1.5M9.05 5.25l3.44-2.19" /></svg>',
            'newsletter_growth' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>',
            'disk_space' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>',
            'request_metrics' => '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>',
        ];

        return $svgs[$id] ?? '<svg class="w-5 h-5" ' . $svgStyle . ' fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21L14.907 18M18 3L21 6M15 15L21 9M10.5 7.5L3 15M3 15V21H9L16.5 13.5" /></svg>';
    }

    protected function getWidgetDescription(string $id): string
    {
        $descriptions = [
            'dashboard_header' => 'Displays personalized greeting, current date, and quick stats summary.',
            'kpi_stats' => 'Displays active customers, today\'s revenue, and pending order statistics.',
            'revenue_chart' => 'Displays an interactive hourly/daily revenue trend analysis chart.',
            'activity_overview' => 'Shows a live feed of recent system events and user actions.',
            'recent_orders' => 'Displays a table showing details of the most recent customer orders.',
            'quick_actions' => 'Provides quick shortcut buttons for common administrative actions.',
            'top_searches' => 'Lists the most frequently searched OEM numbers and keywords.',
            'failed_searches' => 'Tracks search queries that yielded no results to help source missing stock.',
            'alerts' => 'Displays critical system-wide notifications and warning alerts.',
            'health_strip' => 'Monitors real-time database connection, storage, and host system health.',
            'manufacturer_revenue' => 'Compares revenue contributions across key auto parts manufacturers.',
            'customer_growth' => 'Tracks customer registration rates over custom time intervals.',
            'checkout_dropoff' => 'Visualizes drop-off rates at different stages of the checkout process.',
            'sales_by_country' => 'Highlights geographical sales distribution across international regions.',
            'order_status_distribution' => 'Displays a breakdown of active, pending, and completed orders.',
            'payment_method_split' => 'Compares transactions and revenues across payment methods.',
            'recent_activity' => 'Detailed audit log tracking all actions taken by administrators.',
            'disk_space' => 'Monitors disk usage across storage, cache, and log directories.',
            'request_metrics' => 'Displays queue throughput, email delivery rates, and search query volume.',
            'stock_alert' => 'Shows low-stock products requiring immediate attention or restocking.',
            'abandoned_carts' => 'Tracks incomplete checkout sessions for recovery follow-up.',
            'coupon_usage' => 'Analyzes coupon redemption rates, revenue impact, and expiry status.',
            'parts_inquiry' => 'Summarizes pending part inquiries and average response time.',
            'latest_customers' => 'Lists recently registered customers with account details.',
            'manufacturing_stats' => 'Displays production metrics for in-house manufactured components.',
            'newsletter_growth' => 'Tracks subscriber growth, campaign open rates, and engagement trends.',
        ];

        return $descriptions[$id] ?? '';
    }
}
