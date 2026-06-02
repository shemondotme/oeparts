<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $recordTitleAttribute = 'address_line1';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address_line1')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Label')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Name')
                    ->getStateUsing(fn ($record): string => trim("{$record->first_name} {$record->last_name}")),
                Tables\Columns\TextColumn::make('address_line1')
                    ->label('Address')
                    ->limit(30),
                Tables\Columns\TextColumn::make('city')
                    ->label('City'),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->alignCenter(),
            ]);
    }
}
