<?php

namespace App\Filament\Resources\CarModelResource\RelationManagers;

use App\Filament\Support\AdminUi;
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
        return AdminUi::configureTable($table)->recordTitleAttribute('oem_number')
            ->columns([
                Tables\Columns\TextColumn::make('oem_number')
                    ->label('OEM Number')
                    ->copyable()
                    ->copyMessage('OEM number copied')
                    ->fontMono()
                    ->weight('medium')
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('condition.name')
                    ->label('Condition')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->name ?? '—')
                    ->extraAttributes(fn ($record): array => $record->condition ? [
                        'style' => "background-color: {$record->condition->bg_color} !important; color: {$record->condition->text_color} !important;",
                    ] : []),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->getStateUsing(fn ($record): string => format_money($record->price))
                    ->alignEnd()
                    ->fontMono(),
                Tables\Columns\TextColumn::make('is_in_stock')
                    ->label('Stock')
                    ->getStateUsing(fn ($record): string => $record->is_in_stock ? 'In Stock' : 'Out of Stock')
                    ->badge()
                    ->color(fn ($record): string => $record->is_in_stock ? 'success' : 'danger')
                    ->icon(fn ($record): string => $record->is_in_stock ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ]);
    }
}
