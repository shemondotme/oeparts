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
        return 'Manufacturers, products, and stock coverage';
    }

    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -17;

    protected ?string $heading = 'Catalog';

    // Full-width so the 3 stats lay out horizontally (matches the other
    // full-width stat strips) instead of stacking in a half-width column.
    protected int|string|array $columnSpan = 'full';

    public function getStats(): array
    {
        $d = $this->cachedWidgetData(function (): array {
            $top = \App\Models\Manufacturer::withCount(['orderItems' => fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('created_at', '>=', $this->periodStart()))])
                ->orderByDesc('order_items_count')
                ->first();

            $productCount = \App\Models\Product::where('is_active', true)->count();
            $outOfStock = \App\Models\Product::where('is_active', true)->where('is_in_stock', false)->count();
            $outOfStockRate = $productCount > 0 ? round(($outOfStock / $productCount) * 100, 1) : 0;

            return [
                'manufacturers' => \App\Models\Manufacturer::count(),
                'activeManufacturers' => \App\Models\Manufacturer::where('is_active', true)->count(),
                'products' => $productCount,
                'outOfStockRate' => $outOfStockRate,
                'topId' => $top?->id,
                'topName' => $top ? \App\Filament\Support\AdminUi::localizedName($top->name, '—') : null,
                'topOrders' => $top?->order_items_count ?? 0,
            ];
        });

        if ($d['manufacturers'] === 0) {
            return [
                Stat::make('No manufacturers yet', '—')
                    ->description('Add manufacturers and products to track performance')
                    ->descriptionIcon('heroicon-o-building-office-2')
                    ->color('gray')
                    ->url(ManufacturerResource::getUrl('create')),
            ];
        }

        return [
            Stat::make('Manufacturers', $d['manufacturers'])
                ->description("{$d['activeManufacturers']} active")
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('info')
                ->url(ManufacturerResource::getUrl('index')),
            Stat::make('Products', number_format($d['products']))
                ->description("{$d['outOfStockRate']}% out of stock")
                ->descriptionIcon('heroicon-o-cube')
                ->color($d['outOfStockRate'] > 25 ? 'danger' : ($d['outOfStockRate'] > 10 ? 'warning' : 'success')),
            Stat::make('Top Mfr (' . $this->periodLabel() . ')', $d['topName'] ?? '—')
                ->description($d['topOrders'] . ' orders')
                ->descriptionIcon('heroicon-o-trophy')
                ->color('warning')
                ->url($d['topId'] ? ManufacturerResource::getUrl('view', ['record' => $d['topId']]) : null),
        ];
    }
}
