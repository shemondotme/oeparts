<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Concerns\HasWidgetExport;
use App\Filament\Resources\ManufacturerResource;
use App\Models\Manufacturer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopManufacturersRevenue extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use HasWidgetExport;

    public function getDescription(): ?string
    {
        return 'Revenue by manufacturer';
    }

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -26;

    protected static ?string $heading = 'Top Manufacturers by Revenue';

    protected static ?string $maxWidth = '1/2';

    protected function getExportHeaders(): array
    {
        return ['Manufacturer', 'Revenue', 'Orders'];
    }

    protected function getExportRows(): iterable
    {
        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        return Manufacturer::query()
            ->select('manufacturers.id', 'manufacturers.name')
            ->selectSub(function ($q) use ($paidStatuses) {
                $q->selectRaw('COALESCE(SUM(order_items.total_price), 0)')
                    ->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                    ->whereIn('orders.status', $paidStatuses)
                    ->where('orders.created_at', '>=', $this->periodStart());
            }, 'revenue')
            ->selectSub(function ($q) use ($paidStatuses) {
                $q->selectRaw('COUNT(*)')
                    ->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                    ->whereIn('orders.status', $paidStatuses)
                    ->where('orders.created_at', '>=', $this->periodStart());
            }, 'order_count')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($m) => [
                is_array($m->name)
                    ? ($m->name['en'] ?? $m->name[array_key_first($m->name)] ?? '—')
                    : ($m->name ?? '—'),
                format_money($m->revenue),
                $m->order_count,
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->getExportActions()];
    }

    public function table(Table $table): Table
    {
        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        return $table
            ->query(
                Manufacturer::query()
                    ->select('manufacturers.id', 'manufacturers.name', 'manufacturers.slug')
                    // Attribution joins through products.manufacturer_id — the previous
                    // string match on manufacturer_snapshot never hit because
                    // manufacturers.name is a translatable JSON column. Items whose
                    // product was deleted (product_id null) drop out of attribution.
                    ->selectSub(
                        function ($query) use ($paidStatuses) {
                            $query->selectRaw('COALESCE(SUM(order_items.total_price), 0)')
                                ->from('order_items')
                                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                                ->join('products', 'products.id', '=', 'order_items.product_id')
                                ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                                ->whereIn('orders.status', $paidStatuses)
                                ->where('orders.created_at', '>=', $this->periodStart());
                        },
                        'revenue'
                    )
                    ->selectSub(
                        function ($query) use ($paidStatuses) {
                            $query->selectRaw('COUNT(*)')
                                ->from('order_items')
                                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                                ->join('products', 'products.id', '=', 'order_items.product_id')
                                ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                                ->whereIn('orders.status', $paidStatuses)
                                ->where('orders.created_at', '>=', $this->periodStart());
                        },
                        'order_count'
                    )
                    ->orderByDesc('revenue')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Manufacturer')
                    ->searchable()
                    ->getStateUsing(fn ($record): string => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                    ->limit(25),
                Tables\Columns\TextColumn::make('revenue')
                    ->label('Revenue (' . $this->periodLabel() . ')')
                    ->getStateUsing(fn ($record): string => (float) $record->revenue > 0 ? format_money($record->revenue) : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_count')
                    ->label('Orders')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('market_share')
                    ->label('Share')
                    ->getStateUsing(function ($record) use ($paidStatuses): string {
                        $totalRevenue = (float) \App\Models\Order::whereIn('status', $paidStatuses)
                            ->where('created_at', '>=', $this->periodStart())
                            ->sum('grand_total');
                        if ($totalRevenue <= 0) return '—';
                        $share = ((float) $record->revenue / $totalRevenue) * 100;
                        return number_format($share, 1) . '%';
                    })
                    ->alignCenter()
                    ->size('sm'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Manufacturer $record): string => ManufacturerResource::getUrl('view', ['record' => $record]))
                    ->size('sm')
                    ->icon('heroicon-m-eye'),
            ])
            ->emptyState(
                view('filament.widgets.empty-state', [
                    'icon' => 'heroicon-o-building-office-2',
                    'heading' => 'No manufacturer data',
                    'description' => 'Add products to track performance by manufacturer.',
                    'ctaLabel' => 'Add Product',
                    'ctaUrl' => \App\Filament\Resources\ProductResource::getUrl('create'),
                ])
            )
            ->searchable(false)
            ->paginated(false);
    }
}
