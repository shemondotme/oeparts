<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarModelResource\Pages;
use App\Filament\Resources\CarModelResource\RelationManagers;
use App\Filament\Support\AdminUi;
use App\Models\CarModel;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CarModelResource extends Resource
{
    protected static ?string $model = CarModel::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-map';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
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
        $years = range(1970, (int) date('Y') + 1);
        $yearOptions = array_combine($years, $years);

        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Model Details')
                                    ->description('Vehicle model information linked to a manufacturer.')
                                    ->icon('heroicon-o-truck')
                                    ->schema([
                                        Forms\Components\Select::make('manufacturer_id')
                                            ->label('Manufacturer')
                                            ->relationship('manufacturer', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->getOptionLabelFromRecordUsing(fn ($record) => AdminUi::localizedName($record->name))
                                            ->required()
                                            ->helperText('The brand or OEM that produces this vehicle model.'),
                                        Forms\Components\TextInput::make('name')
                                            ->label('Model Name')
                                            ->placeholder('e.g. Golf, 3 Series, C-Class')
                                            ->required()
                                            ->maxLength(200)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                if (filled($state) && blank($get('slug'))) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            })
                                            ->helperText('The commercial name of this vehicle model.'),
                                        Forms\Components\TextInput::make('slug')
                                            ->label('URL Slug')
                                            ->placeholder('e.g. volkswagen-golf')
                                            ->helperText('Used in vehicle model page URLs. Auto-filled from the model name when empty.')
                                            ->required()
                                            ->maxLength(200)
                                            ->unique(ignoreRecord: true)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),

                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Years & Visibility')
                                    ->icon('heroicon-o-calendar')
                                    ->description('Production year range and display settings for this model.')
                                    ->schema([
                                        Forms\Components\Select::make('year_from')
                                            ->label('Production Start Year')
                                            ->options($yearOptions)
                                            ->nullable()
                                            ->searchable()
                                            ->native(false)
                                            ->helperText('The first model year (e.g. 2015).'),
                                        Forms\Components\Select::make('year_to')
                                            ->label('Production End Year')
                                            ->options($yearOptions)
                                            ->nullable()
                                            ->searchable()
                                            ->native(false)
                                            ->rule(function (callable $get) {
                                                return function (string $attribute, $value, callable $fail) use ($get) {
                                                    $yearFrom = $get('year_from');
                                                    if ($yearFrom && $value && (int) $value < (int) $yearFrom) {
                                                        $fail('End year must be greater than or equal to start year.');
                                                    }
                                                };
                                            })
                                            ->helperText('The last model year. Leave empty if still in production.'),
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Model Active')
                                            ->helperText('Inactive models are hidden from the storefront and part compatibility search.')
                                            ->default(true),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label('Display Order')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Lower numbers appear first in model listings.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('manufacturer'))
            ->columns([
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Manufacturer')
                    ->getStateUsing(fn (CarModel $record): string => $record->manufacturer ? AdminUi::localizedName($record->manufacturer->name) : '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('manufacturer', function ($q) use ($search) {
                            $q->where('name->en', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->limit(22)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Model')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->description(fn (CarModel $record): ?string => $record->slug ?: null),
                Tables\Columns\TextColumn::make('year_range')
                    ->label('Years')
                    ->getStateUsing(function (CarModel $record): string {
                        if ($record->year_from && $record->year_to) {
                            return "{$record->year_from}–{$record->year_to}";
                        }

                        return $record->year_from ? (string) $record->year_from : ($record->year_to ? (string) $record->year_to : '—');
                    })
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->fontMono()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (CarModel $record): string => $record->is_active ? 'Active' : 'Inactive')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->fontMono()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('manufacturer_id')
                    ->relationship('manufacturer', 'name')
                    ->label('Manufacturer')
                    ->getOptionLabelFromRecordUsing(fn ($record) => AdminUi::localizedName($record->name))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('Filter models by brand or OEM.')
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Model Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->columnSpan(1),
            ])
            ->filtersFormColumns(2)
            ->actions(AdminUi::recordActions())
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::impactBulkAction(
                        name: 'activate',
                        label: 'Activate',
                        color: 'success',
                        icon: 'heroicon-o-check-circle',
                        summary: fn ($record): ?array => $record->is_active
                            ? null
                            : [
                                'key' => $record->name,
                                'old' => 'Inactive',
                                'new' => 'Active',
                            ],
                        action: function ($records): void {
                            foreach ($records as $record) {
                                $record->is_active = true;
                                $record->save();
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Models activated')
                                ->success()
                                ->send();
                        },
                    ),
                    AdminUi::impactBulkAction(
                        name: 'deactivate',
                        label: 'Deactivate',
                        color: 'danger',
                        icon: 'heroicon-o-x-circle',
                        summary: fn ($record): ?array => !$record->is_active
                            ? null
                            : [
                                'key' => $record->name,
                                'old' => 'Active',
                                'new' => 'Inactive',
                            ],
                        action: function ($records): void {
                            foreach ($records as $record) {
                                $record->is_active = false;
                                $record->save();
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Models deactivated')
                                ->success()
                                ->send();
                        },
                    ),
                    AdminUi::exportCsvBulkAction('Export Models', [
                        'manufacturer.name' => 'Manufacturer',
                        'name' => 'Model',
                        'year_range' => 'Years',
                        'products_count' => 'Products',
                        'is_active' => 'Active',
                        'sort_order' => 'Sort Order',
                    ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No vehicle models added yet')
            ->emptyStateDescription('Add vehicle models to link parts to compatible cars and enable vehicle-based search.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Model')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCarModels::route('/'),
            'create' => Pages\CreateCarModel::route('/create'),
            'view'   => Pages\ViewCarModel::route('/{record}'),
            'edit'   => Pages\EditCarModel::route('/{record}/edit'),
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
        return ['name', 'slug'];
    }
}
