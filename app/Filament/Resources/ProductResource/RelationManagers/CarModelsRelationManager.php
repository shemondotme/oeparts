<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

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
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Model Name'),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Manufacturer')
                    ->getStateUsing(fn ($record): string => $record->manufacturer ? (is_array($record->manufacturer->name) ? ($record->manufacturer->name['en'] ?? $record->manufacturer->name[array_key_first($record->manufacturer->name)] ?? '—') : ($record->manufacturer->name ?? '—')) : '—')
                    ->limit(20),
                Tables\Columns\TextColumn::make('year_from')
                    ->label('Year From')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('year_to')
                    ->label('Year To')
                    ->placeholder('—'),
            ])
            ->paginated(false);
    }
}
