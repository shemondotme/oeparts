<?php

namespace App\Filament\Resources\RefundRequestResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrderSummaryRelationManager extends RelationManager
{
    protected static string $relationship = 'order';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value) {
                        'pending'         => 'warning',
                        'paid'            => 'info',
                        'processing'      => 'primary',
                        'shipped'         => 'success',
                        'delivered'       => 'success',
                        'cancelled'       => 'danger',
                        'refund_requested'=> 'warning',
                        'refunded'        => 'gray',
                        default           => 'gray',
                    }),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->getStateUsing(fn ($record): string => format_money($record->grand_total)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i'),
            ])
            ->paginated(false);
    }
}
