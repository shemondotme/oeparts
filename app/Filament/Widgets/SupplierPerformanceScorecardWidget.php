<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Models\Manufacturer;
use App\Services\AdminCacheService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class SupplierPerformanceScorecardWidget extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use HasWidgetExport;

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -10;

    protected static ?string $heading = 'Supplier Performance';

    protected static ?string $maxWidth = '1/2';

    protected function getExportHeaders(): array
    {
        return ['Manufacturer', 'Orders', 'Avg Order Value', 'Avg Delivery Days', 'On-Time %', 'Return %'];
    }

    protected function getExportRows(): iterable
    {
        $expectedDays = (int) settings('orders.expected_delivery_days', 5);
        $periodStart  = $this->periodStart();

        return Manufacturer::query()
            ->select('manufacturers.id', 'manufacturers.name')
            ->selectSub(fn ($q) => $q->selectRaw('COUNT(DISTINCT orders.id)')
                ->from('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                ->where('orders.created_at', '>=', $periodStart), 'order_count')
            ->selectSub(fn ($q) => $q->selectRaw('COALESCE(AVG(orders.grand_total), 0)')
                ->from('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                ->whereIn('orders.status', ['paid', 'processing', 'shipped', 'delivered'])
                ->where('orders.created_at', '>=', $periodStart), 'avg_order_value')
            ->selectSub(fn ($q) => $q->selectRaw('COALESCE(AVG(DATEDIFF(orders.updated_at, orders.created_at)), 0)')
                ->from('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                ->where('orders.status', 'delivered')
                ->where('orders.created_at', '>=', $periodStart), 'avg_delivery_days')
            ->selectSub(fn ($q) => $q->selectRaw(
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
                ->where('orders.created_at', '>=', $periodStart), 'on_time_rate')
            ->selectSub(fn ($q) => $q->selectRaw(
                    "CASE COUNT(DISTINCT orders.id) WHEN 0 THEN NULL ELSE
                     ROUND(SUM(CASE WHEN orders.status IN ('refund_requested', 'refunded') THEN 1 ELSE 0 END) * 100.0 / COUNT(DISTINCT orders.id), 1)
                     END"
                )
                ->from('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                ->where('orders.created_at', '>=', $periodStart), 'return_rate')
            ->having('order_count', '>', 0)
            ->orderByDesc('order_count')
            ->get()
            ->map(fn ($m) => [
                is_array($m->name)
                    ? ($m->name['en'] ?? $m->name[array_key_first($m->name)] ?? '—')
                    : ($m->name ?? '—'),
                $m->order_count,
                format_money($m->avg_order_value ?? 0),
                $m->avg_delivery_days ? number_format((float) $m->avg_delivery_days, 1) . 'd' : '—',
                $m->on_time_rate !== null ? $m->on_time_rate . '%' : '—',
                $m->return_rate !== null ? $m->return_rate . '%' : '—',
            ]);
    }

    protected function getTableHeaderActions(): array
    {
        return [
            $this->getExportActions(),
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

                Tables\Columns\ViewColumn::make('on_time_rate')
                    ->label('On-Time')
                    ->view('filament.widgets.partials.rate-bar')
                    ->viewData([
                        'colorFor' => fn (float $rate): string => $rate >= 90
                            ? 'var(--accent-success)'
                            : ($rate >= 70 ? 'var(--accent-warning)' : 'var(--accent-danger)'),
                        'decimals' => 0,
                        'widthScale' => 1,
                    ])
                    ->alignCenter(),

                Tables\Columns\ViewColumn::make('return_rate')
                    ->label('Returns')
                    ->view('filament.widgets.partials.rate-bar')
                    ->viewData([
                        // Lower return rate is better — red above 10%, amber 5-10%, green < 5%
                        'colorFor' => fn (float $rate): string => $rate >= 10
                            ? 'var(--accent-danger)'
                            : ($rate >= 5 ? 'var(--accent-warning)' : 'var(--accent-success)'),
                        'decimals' => 1,
                        'widthScale' => 5,
                    ])
                    ->alignCenter(),
            ])
            ->emptyState(
                view('filament.widgets.empty-state', [
                    'icon'        => 'heroicon-o-truck',
                    'heading'     => 'No supplier data yet',
                    'description' => 'Add products and complete orders to see manufacturer performance metrics.',
                    'ctaLabel'    => 'Add Product',
                    'ctaUrl'      => \App\Filament\Resources\ProductResource::getUrl('create'),
                ])
            )
            ->searchable(false)
            ->paginated(false)
            ->striped();
    }
}
