<?php

namespace App\Filament\Resources\ShippingZoneResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'methods';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->label('Name (JSON)')
                    ->helperText('e.g. {"en": "Standard Shipping", "de": "Standardversand"}')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Description (JSON)')
                    ->rows(2)
                    ->nullable(),
                Forms\Components\TextInput::make('flat_rate')
                    ->label('Flat Rate (€)')
                    ->numeric()
                    ->required()
                    ->step(0.01)
                    ->prefix('€'),
                Forms\Components\TextInput::make('free_shipping_threshold')
                    ->label('Free Shipping Threshold (€)')
                    ->numeric()
                    ->nullable()
                    ->step(0.01)
                    ->prefix('€')
                    ->helperText('Leave empty for no free shipping'),
                Grid::make(2)->schema([
                    Forms\Components\TextInput::make('estimated_days_min')
                        ->label('Min Days')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                    Forms\Components\TextInput::make('estimated_days_max')
                        ->label('Max Days')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                ]),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Method')
                    ->getStateUsing(fn ($record): string => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('flat_rate')
                    ->label('Rate')
                    ->money('EUR')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('free_shipping_threshold')
                    ->label('Free Threshold')
                    ->money('EUR')
                    ->alignEnd()
                    ->getStateUsing(fn ($record): string => $record->free_shipping_threshold ?: '—'),
                Tables\Columns\TextColumn::make('estimated_days_min')
                    ->label('Min Days')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('estimated_days_max')
                    ->label('Max Days')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->paginated(false);
    }
}
