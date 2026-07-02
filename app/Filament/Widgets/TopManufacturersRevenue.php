<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\ManufacturerResource;
use App\Models\Manufacturer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopManufacturersRevenue extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Revenue by manufacturer';
    }

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -26;

    protected static ?string $heading = 'Top Manufacturers by Revenue';

    protected int | string | array $columnSpan = 'full';

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(ManufacturerResource::getUrl('index')),
        ];
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
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex()
                    ->badge()
                    ->icon(fn (mixed $state): ?string => (int) $state === 1 ? 'heroicon-m-trophy' : null)
                    ->color(fn (mixed $state): string => match ((int) $state) {
                        1 => 'warning',
                        2, 3 => 'primary',
                        default => 'gray',
                    })
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Manufacturer')
                    ->searchable()
                    ->getStateUsing(fn ($record): string => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                    ->limit(25),
                Tables\Columns\TextColumn::make('revenue')
                    ->label('Revenue (' . $this->periodLabel() . ')')
                    ->getStateUsing(fn ($record): string => (float) $record->revenue > 0 ? format_money($record->revenue) : '—')
                    ->sortable()
                    ->summarize(
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->label('Top 10 total')
                            ->formatStateUsing(fn ($state): string => format_money($state))
                    ),
                Tables\Columns\TextColumn::make('order_count')
                    ->label('Orders')
                    ->alignCenter()
                    ->summarize(
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->label('Total')
                    ),
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
            ->striped()
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading('No revenue data yet')
            ->emptyStateDescription('Manufacturer revenue appears once orders are placed.')
            ->searchable(false)
            ->paginated(false);
    }
}
