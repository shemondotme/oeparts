<?php

namespace App\Filament\Resources\CouponResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $recordTitleAttribute = 'used_at';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('used_at')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->getStateUsing(fn ($record): string => $record->user?->name ?? 'Guest')
                    ->limit(25),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('used_at')
                    ->label('Used At')
                    ->dateTime('M j, Y H:i'),
            ])
            ->defaultSort('used_at', 'desc');
    }
}
