<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Manufacturer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopManufacturersRevenue extends TableWidget
{
    protected static ?int $sort = -15;

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
                    ->selectSub(
                        function ($query) use ($paidStatuses) {
                            $query->selectRaw('COALESCE(SUM(order_items.total_price), 0)')
                                ->from('order_items')
                                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                                ->whereColumn('order_items.manufacturer_snapshot', 'manufacturers.name')
                                ->whereIn('orders.status', $paidStatuses)
                                ->where('orders.created_at', '>=', now()->subDays(30));
                        },
                        'revenue'
                    )
                    ->selectSub(
                        function ($query) use ($paidStatuses) {
                            $query->selectRaw('COUNT(*)')
                                ->from('order_items')
                                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                                ->whereColumn('order_items.manufacturer_snapshot', 'manufacturers.name')
                                ->whereIn('orders.status', $paidStatuses)
                                ->where('orders.created_at', '>=', now()->subDays(30));
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
                    ->label('Revenue (30d)')
                    ->getStateUsing(fn ($record): string => format_money($record->revenue))
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_count')
                    ->label('Orders')
                    ->alignCenter(),
            ])
            ->searchable(false)
            ->paginated(false);
    }
}
