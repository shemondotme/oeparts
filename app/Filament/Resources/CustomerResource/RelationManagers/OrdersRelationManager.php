<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Enums\OrderStatus;
use App\Filament\Support\AdminUi;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)->recordTitleAttribute('order_number')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->copyable()
                    ->copyMessage('Order number copied')
                    ->fontMono()
                    ->weight('medium')
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (OrderStatus $state): string => AdminUi::orderStatusColor($state))
                    ->icon(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Pending => 'heroicon-o-clock',
                        OrderStatus::Paid => 'heroicon-o-check-circle',
                        OrderStatus::Processing => 'heroicon-o-arrow-path',
                        OrderStatus::Shipped => 'heroicon-o-truck',
                        OrderStatus::Delivered => 'heroicon-o-check-badge',
                        OrderStatus::Cancelled => 'heroicon-o-x-circle',
                        OrderStatus::RefundRequested => 'heroicon-o-arrow-uturn-left',
                        OrderStatus::Refunded => 'heroicon-o-banknotes',
                    }),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->getStateUsing(fn ($record): string => format_money($record->grand_total))
                    ->alignEnd()
                    ->fontMono(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
