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

    public function getDescription(): ?string
    {
        return 'Revenue by manufacturer';
    }

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -29;

    protected static ?string $heading = 'Top Manufacturers by Revenue';

    protected static ?string $maxWidth = '1/2';

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
                    ->getStateUsing(fn ($record): string => format_money($record->revenue))
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_count')
                    ->label('Orders')
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Manufacturer $record): string => ManufacturerResource::getUrl('view', ['record' => $record]))
                    ->size('sm')
                    ->icon('heroicon-m-eye'),
            ])
            ->searchable(false)
            ->paginated(false);
    }
}
