<?php

namespace App\Filament\Widgets;

use App\Filament\Clusters\Reports;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\FailedSearchLogResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProductResource;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    public function getDescription(): ?string
    {
        return 'Common admin operations';
    }

    use \App\Filament\Widgets\Concerns\HasWidgetRoles;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.quick-actions-widget';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -34;

    protected function getViewData(): array
    {
        // The widget is visible to every role; each shortcut only renders
        // when the target surface is itself accessible to the admin.
        $actions = [];

        if (ProductResource::canCreate()) {
            $actions[] = [
                'label' => 'New Product',
                'icon' => 'heroicon-o-plus-circle',
                'color' => 'warning',
                'url' => ProductResource::getUrl('create'),
            ];
        }

        if (OrderResource::canCreate()) {
            $actions[] = [
                'label' => 'New Order',
                'icon' => 'heroicon-o-shopping-bag',
                'color' => 'info',
                'url' => OrderResource::getUrl('create'),
            ];
        }

        if (Reports::canAccess()) {
            $actions[] = [
                'label' => 'View Reports',
                'icon' => 'heroicon-o-presentation-chart-line',
                'color' => 'success',
                'url' => Reports::getUrl(),
            ];
        }

        if (Settings::canAccess()) {
            $actions[] = [
                'label' => 'System Settings',
                'icon' => 'heroicon-o-cog-6-tooth',
                'color' => 'gray',
                'url' => Settings::getUrl(),
            ];
        }

        if (\App\Filament\Resources\FailedSearchLogResource::canViewAny()) {
            $actions[] = [
                'label' => 'Failed Search Logs',
                'icon' => 'heroicon-o-magnifying-glass-circle',
                'color' => 'danger',
                'url' => \App\Filament\Resources\FailedSearchLogResource::getUrl('index'),
            ];
        }

        return ['actions' => $actions];
    }
}
