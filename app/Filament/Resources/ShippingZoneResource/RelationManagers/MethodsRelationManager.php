<?php

namespace App\Filament\Resources\ShippingZoneResource\RelationManagers;

use App\Filament\Support\AdminUi;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions;
use Filament\Tables\Table;

class MethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'methods';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                AdminUi::translatableTabs('Locales', [
                    'name' => [
                        'label' => 'Method Name',
                        'required' => true,
                    ],
                    'description' => [
                        'label' => 'Description',
                        'type' => 'textarea',
                        'rows' => 2,
                    ],
                ]),
                Forms\Components\TextInput::make('flat_rate')
                    ->label('Flat Rate (€)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('€'),
                Forms\Components\TextInput::make('free_shipping_threshold')
                    ->label('Free Shipping Threshold (€)')
                    ->numeric()
                    ->nullable()
                    ->minValue(0)
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
                    ->default(0)
                    ->minValue(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Method')
                    ->getStateUsing(fn ($record): string => AdminUi::localizedName($record->name))
                    ->searchable(),
                Tables\Columns\TextColumn::make('flat_rate')
                    ->label('Rate')
                    ->getStateUsing(fn ($record): string => format_money($record->flat_rate))
                    ->fontMono()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('free_shipping_threshold')
                    ->label('Free Threshold')
                    ->getStateUsing(fn ($record): string => filled($record->free_shipping_threshold) ? format_money($record->free_shipping_threshold) : '—')
                    ->fontMono()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('estimated_days')
                    ->label('Delivery')
                    ->alignCenter()
                    ->getStateUsing(fn ($record): string => "{$record->estimated_days_min}–{$record->estimated_days_max} days")
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->fontMono()
                    ->alignCenter()
                    ->sortable(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                ...AdminUi::recordActionsWithoutView(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }
}
