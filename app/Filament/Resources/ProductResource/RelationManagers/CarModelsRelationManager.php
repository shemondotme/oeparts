<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

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
            ->modifyQueryUsing(fn ($query) => $query->with('manufacturer'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Model Name'),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Manufacturer')
                    ->getStateUsing(fn ($record): string => $record->manufacturer ? AdminUi::localizedName($record->manufacturer->name) : '—')
                    ->limit(20),
                Tables\Columns\TextColumn::make('year_from')
                    ->label('Year From')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('year_to')
                    ->label('Year To')
                    ->placeholder('—'),
            ]);
        // Pagination re-enabled (was ->paginated(false)): a widely-fitted part
        // (e.g. a common brake pad) can match hundreds of car models — an
        // unbounded, unpaginated list is a real risk, not just a theoretical one.
    }
}
