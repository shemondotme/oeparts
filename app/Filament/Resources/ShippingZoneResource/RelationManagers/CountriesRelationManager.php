<?php

namespace App\Filament\Resources\ShippingZoneResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CountriesRelationManager extends RelationManager
{
    protected static string $relationship = 'countries';

    protected static ?string $recordTitleAttribute = 'country_name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('country_code')
                    ->label('Country')
                    ->options(config('countries', []))
                    ->searchable()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $countries = config('countries', []);
                        $set('country_name', $countries[$state] ?? $state);
                    })
                    ->reactive(),
                Forms\Components\TextInput::make('country_name')
                    ->label('Country Name')
                    ->maxLength(100)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('country_name')
            ->columns([
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Code')
                    ->badge()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('country_name')
                    ->label('Country')
                    ->searchable(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
                Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelect(function ($select) {
                        return $select->options(function () {
                            $existing = $this->getOwnerRecord()->countries->pluck('country_code')->toArray();
                            $countries = config('countries', []);
                            return collect($countries)->except($existing)->toArray();
                        });
                    }),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DetachAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DetachBulkAction::make(),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated(false);
    }
}
