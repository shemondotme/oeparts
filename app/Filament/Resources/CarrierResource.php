<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarrierResource\Pages;
use App\Models\Carrier;
use Filament\Forms;
use Filament\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CarrierResource extends Resource
{
    protected static ?string $model = Carrier::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Commerce';
    }

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Carrier Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Carrier Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('tracking_url')
                            ->label('Tracking URL')
                            ->helperText('Use {tracking_no} as placeholder for the tracking number')
                            ->url()
                            ->maxLength(500),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tracking_url')
                    ->limit(40)
                    ->copyable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCarriers::route('/'),
            'create' => Pages\CreateCarrier::route('/create'),
            'edit' => Pages\EditCarrier::route('/{record}/edit'),
        ];
    }
}
