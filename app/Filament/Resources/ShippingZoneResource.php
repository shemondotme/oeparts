<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingZoneResource\Pages;
use App\Filament\Resources\ShippingZoneResource\RelationManagers;
use App\Filament\Support\AdminUi;
use App\Models\ShippingZone;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-globe-alt';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Commerce';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Zone Details')
                                    ->icon('heroicon-o-globe-alt')
                                    ->description('Define the shipping region shown at checkout.')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Zone Name')
                                            ->placeholder('e.g. Europe, Baltics, International')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('A descriptive name for this shipping region. Countries and methods are assigned on the next tab.'),
                                    ]),
                            ]),
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Settings')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Zone visibility and display ordering.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Zone Active')
                                            ->helperText('Inactive zones are hidden from the checkout page.')
                                            ->default(true),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label('Display Order')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Lower numbers appear first at checkout.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->withCount(['countries', 'methods']))
            ->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Zone')
                ->searchable()
                ->sortable()
                ->weight(FontWeight::Medium)
                ->description(fn (ShippingZone $record): string => "{$record->countries_count} countries · {$record->methods_count} methods"),
            Tables\Columns\TextColumn::make('countries_count')
                ->label('Countries')
                ->badge()
                ->color('info')
                ->icon('heroicon-o-globe-alt')
                ->alignCenter(),
            Tables\Columns\TextColumn::make('methods_count')
                ->label('Methods')
                ->badge()
                ->color('success')
                ->icon('heroicon-o-truck')
                ->alignCenter(),
            Tables\Columns\TextColumn::make('is_active')
                ->label('Active')
                ->badge()
                ->alignCenter()
                ->getStateUsing(fn (ShippingZone $record): string => $record->is_active ? 'Active' : 'Inactive')
                ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->fontMono()
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Zone Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
                Tables\Filters\Filter::make('countries')
                    ->label('Countries Range')
                    ->form([
                        Forms\Components\TextInput::make('countries_from')
                            ->label('Min')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('countries_to')
                            ->label('Max')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['countries_from'], fn ($q, $v) => $q->having('countries_count', '>=', $v))
                            ->when($data['countries_to'], fn ($q, $v) => $q->having('countries_count', '<=', $v));
                    })
                    ->columns(2)
                    ->columnSpan(2),
                Tables\Filters\Filter::make('methods')
                    ->label('Methods Range')
                    ->form([
                        Forms\Components\TextInput::make('methods_from')
                            ->label('Min')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('methods_to')
                            ->label('Max')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['methods_from'], fn ($q, $v) => $q->having('methods_count', '>=', $v))
                            ->when($data['methods_to'], fn ($q, $v) => $q->having('methods_count', '<=', $v));
                    })
                    ->columns(2)
                    ->columnSpan(2),
            ])
            ->actions(AdminUi::recordActions())
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Zones', [
                    'name' => 'Zone',
                    'countries_count' => 'Countries',
                    'methods_count' => 'Methods',
                    'is_active' => 'Active',
                    'sort_order' => 'Sort Order',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->emptyStateIcon('heroicon-o-globe-alt')
            ->emptyStateHeading('No shipping zones configured')
            ->emptyStateDescription('Create your first shipping zone, then assign countries and delivery methods to it.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Zone')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CountriesRelationManager::class,
            RelationManagers\MethodsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListShippingZones::route('/'),
            'create' => Pages\CreateShippingZone::route('/create'),
            'view'   => Pages\ViewShippingZone::route('/{record}'),
            'edit'   => Pages\EditShippingZone::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('is_active', true)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
