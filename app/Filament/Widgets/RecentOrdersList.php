<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentOrdersList extends TableWidget
{
    protected static ?int $sort = -17;

    protected static ?string $heading = 'Recent Orders';

    protected static ?string $maxWidth = '1/3';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with(['user'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Order number copied')
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->getStateUsing(fn (Order $record): string => $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—')
                    ->limit(20),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->getStateUsing(fn (Order $record): string => format_money($record->grand_total))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => match ($state instanceof \App\Enums\OrderStatus ? $state->value : $state) {
                        'pending' => 'warning',
                        'paid' => 'info',
                        'processing' => 'primary',
                        'shipped' => 'success',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'refund_requested' => 'warning',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (Order $record): string => \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->searchable(false)
            ->paginated(false);
    }
}
