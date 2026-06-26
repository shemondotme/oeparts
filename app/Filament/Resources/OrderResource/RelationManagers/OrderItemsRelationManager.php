<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Filament\Support\AdminUi;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'oem_number_snapshot';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('oem_number_snapshot')
                    ->label('OEM Number')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('manufacturer_snapshot')
                    ->label('Manufacturer')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('condition_snapshot')
                    ->label('Condition')
                    ->required()
                    ->maxLength(50),
                Forms\Components\TextInput::make('quantity')
                    ->label('Quantity')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Forms\Components\TextInput::make('unit_price')
                    ->label('Unit Price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('€'),
                Forms\Components\TextInput::make('total_price')
                    ->label('Total Price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('€'),
            ]);
    }

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)->recordTitleAttribute('oem_number_snapshot')
            ->columns([
                AdminUi::oemColumn('oem_number_snapshot'),
                Tables\Columns\TextColumn::make('manufacturer_snapshot')
                    ->label('Manufacturer'),
                Tables\Columns\TextColumn::make('condition_snapshot')
                    ->label('Condition')
                    ->badge(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->getStateUsing(fn ($record): string => format_money($record->unit_price))
                    ->alignEnd()
                    ->fontMono(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->getStateUsing(fn ($record): string => format_money($record->total_price))
                    ->alignEnd()
                    ->fontMono(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
