<?php

namespace App\Filament\Resources\ManufacturerResource\RelationManagers;

use App\Filament\Support\AdminUi;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CarModelsRelationManager extends RelationManager
{
    protected static string $relationship = 'carModels';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Model')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->fontMono()
                    ->limit(30),
                Tables\Columns\TextColumn::make('year_from')
                    ->label('Year From')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('year_to')
                    ->label('Year To')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn ($record): string => $record->is_active ? 'Active' : 'Inactive')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->defaultSort('name', 'asc');
    }
}
