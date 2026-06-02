<?php

namespace App\Filament\Resources\ManufacturerResource\RelationManagers;

use App\Enums\ProductCondition;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $recordTitleAttribute = 'oem_number';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('oem_number')
            ->columns([
                Tables\Columns\TextColumn::make('oem_number')
                    ->label('OEM Number')
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('condition')
                    ->label('Condition')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value) {
                        'new'            => 'success',
                        'used_grade_a'   => 'info',
                        'used_grade_b'   => 'warning',
                        'used_grade_c'   => 'gray',
                        'remanufactured' => 'primary',
                        'aftermarket'    => 'danger',
                        'new_old_stock'  => 'info',
                        default          => 'gray',
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->getStateUsing(fn ($record): string => format_money($record->price)),
                Tables\Columns\TextColumn::make('is_in_stock')
                    ->label('Stock')
                    ->getStateUsing(fn ($record): string => $record->is_in_stock ? 'In Stock' : 'Out of Stock')
                    ->badge()
                    ->color(fn ($record): string => $record->is_in_stock ? 'success' : 'danger'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
