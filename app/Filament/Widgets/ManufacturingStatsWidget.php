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

            $productCount = \App\Models\Product::where('is_active', true)->count();
            $defective = \App\Models\Product::where('is_in_stock', false)->count();
            $defectRate = $productCount > 0 ? round(($defective / $productCount) * 100, 1) : 0;

            return [
                'manufacturers' => \App\Models\Manufacturer::count(),
                'activeManufacturers' => \App\Models\Manufacturer::where('is_active', true)->count(),
                'products' => $productCount,
                'defectRate' => $defectRate,
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
                ->description("Defect rate: {$d['defectRate']}%")
                ->descriptionIcon('heroicon-o-cube')
                ->color($d['defectRate'] > 5 ? 'danger' : 'success'),
            Stat::make('Top Mfr (' . $this->periodLabel() . ')', $d['topName'] ?? '—')
                ->description($d['topOrders'] . ' orders')
                ->descriptionIcon('heroicon-o-trophy')
                ->color('warning')
                ->url($d['topId'] ? ManufacturerResource::getUrl('view', ['record' => $d['topId']]) : null),
        ];
    }
}
