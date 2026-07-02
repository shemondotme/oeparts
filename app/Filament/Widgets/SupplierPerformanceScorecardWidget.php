<?php

namespace App\Filament\Widgets;

use App\Models\Manufacturer;
use App\Services\AdminCacheService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class SupplierPerformanceScorecardWidget extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -10;

    protected static ?string $heading = 'Supplier Performance';

    protected static ?string $maxWidth = 'full';

    // Full-width — a 6-column metric scorecard reads much better across the
    // full row than cramped into a half-width column.
    protected int|string|array $columnSpan = 'full';

    private function periodStart(): string
    {
        return now()->subDays(90)->startOfDay()->toDateTimeString();
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(\App\Filament\Resources\ManufacturerResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        $expectedDays = (int) settings('orders.expected_delivery_days', 5);
        $periodStart  = $this->periodStart();

        $query = Manufacturer::query()
            ->select('manufacturers.id', 'manufacturers.name', 'manufacturers.slug')
            // Total orders attributed to this manufacturer in the period
            ->selectSub(function ($q) use ($periodStart) {
                $q->selectRaw('COUNT(DISTINCT orders.id)')
                    ->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                    ->where('orders.created_at', '>=', $periodStart);
            }, 'order_count')
            // Average order value (per-order total, not per-item)
            ->selectSub(function ($q) use ($periodStart) {
                $q->selectRaw('COALESCE(AVG(orders.grand_total), 0)')
                    ->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                    ->whereIn('orders.status', ['paid', 'processing', 'shipped', 'delivered'])
                    ->where('orders.created_at', '>=', $periodStart);
            }, 'avg_order_value')
            // Avg fulfillment days: created_at → updated_at for delivered orders
            ->selectSub(function ($q) use ($periodStart) {
                $q->selectRaw('COALESCE(AVG(DATEDIFF(orders.updated_at, orders.created_at)), 0)')
                    ->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                    ->where('orders.status', 'delivered')
                    ->where('orders.created_at', '>=', $periodStart);
            }, 'avg_delivery_days')
            // On-time rate: % of delivered orders within expectedDays
            ->selectSub(function ($q) use ($periodStart, $expectedDays) {
                $q->selectRaw(
                    'CASE COUNT(*) WHEN 0 THEN NULL ELSE
                     ROUND(SUM(CASE WHEN DATEDIFF(orders.updated_at, orders.created_at) <= ? THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1)
                     END',
                    [$expectedDays]
                )
                    ->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                    ->where('orders.status', 'delivered')
                    ->where('orders.created_at', '>=', $periodStart);
            }, 'on_time_rate')
            // Return rate: % of orders in refund_requested or refunded
            ->selectSub(function ($q) use ($periodStart) {
                $q->selectRaw(
                    'CASE COUNT(DISTINCT orders.id) WHEN 0 THEN NULL ELSE
                     ROUND(SUM(CASE WHEN orders.status IN (\'refund_requested\', \'refunded\') THEN 1 ELSE 0 END) * 100.0 / COUNT(DISTINCT orders.id), 1)
                     END'
                )
                    ->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                    ->where('orders.created_at', '>=', $periodStart);
            }, 'return_rate')
            ->having('order_count', '>', 0)
            ->orderByDesc('order_count')
            ->limit(10);

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Manufacturer')
                    ->getStateUsing(fn ($record): string => is_array($record->name)
                        ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—')
                        : ($record->name ?? '—'))
                    ->limit(20),

                Tables\Columns\TextColumn::make('order_count')
                    ->label('Orders')
                    ->alignCenter()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('avg_order_value')
                    ->label('Avg Value')
                    ->getStateUsing(fn ($record): string => format_money($record->avg_order_value ?? 0))
                    ->alignEnd()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('avg_delivery_days')
                    ->label('Avg Days')
                    ->getStateUsing(fn ($record): string => $record->avg_delivery_days
                        ? number_format((float) $record->avg_delivery_days, 1) . 'd'
                        : '—')
                    ->alignCenter()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('on_time_rate')
                    ->label('On-Time')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 0) . '%' : '—')
                    ->color(fn ($state): string => $state === null ? 'gray' : ((float) $state >= 90 ? 'success' : ((float) $state >= 70 ? 'warning' : 'danger')))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('return_rate')
                    // Lower return rate is better — red above 10%, amber 5-10%, green < 5%
                    ->label('Returns')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 1) . '%' : '—')
                    ->color(fn ($state): string => $state === null ? 'gray' : ((float) $state >= 10 ? 'danger' : ((float) $state >= 5 ? 'warning' : 'success')))
                    ->alignCenter(),
            ])
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No supplier data yet')
            ->emptyStateDescription('Performance appears once manufacturers have orders.')
            ->searchable(false)
            ->paginated(false)
            ->striped();
    }
}
