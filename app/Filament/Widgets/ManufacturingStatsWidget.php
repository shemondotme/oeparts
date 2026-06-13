<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ManufacturerResource;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ManufacturingStatsWidget extends StatsOverviewWidget
{
    public function getDescription(): ?string
    {
        return 'OEM manufacturer statistics';
    }

    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -17;

    protected ?string $heading = 'Manufacturing';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    public function getStats(): array
    {
        $d = $this->cachedWidgetData(function (): array {
            $top = \App\Models\Manufacturer::withCount(['orderItems' => fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('created_at', '>=', $this->periodStart()))])
                ->orderByDesc('order_items_count')
                ->first();

            return [
                'manufacturers' => \App\Models\Manufacturer::count(),
                'activeManufacturers' => \App\Models\Manufacturer::where('is_active', true)->count(),
                'topId' => $top?->id,
                'topName' => $top ? \App\Filament\Support\AdminUi::localizedName($top->name, '—') : null,
                'topOrders' => $top?->order_items_count ?? 0,
            ];
        });

        return [
            Stat::make('Total Manufacturers', $d['manufacturers'])
                ->description("{$d['activeManufacturers']} active")
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('info')
                ->url(ManufacturerResource::getUrl('index')),
            Stat::make('Top Manufacturer (' . $this->periodLabel() . ')', $d['topName'] ?? '—')
                ->description($d['topOrders'] . ' orders')
                ->descriptionIcon('heroicon-o-trophy')
                ->color('warning')
                ->url($d['topId'] ? ManufacturerResource::getUrl('view', ['record' => $d['topId']]) : null),
        ];
    }
}
