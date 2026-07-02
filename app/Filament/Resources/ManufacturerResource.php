<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManufacturerResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Manufacturer;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManufacturerResource extends Resource
{
    protected static ?string $model = Manufacturer::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-building-office-2';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
    }

    public static function getNavigationSort(): ?int
    {
        return 15;
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
                                Section::make('Brand Identity')
                                    ->description('How this manufacturer appears in the catalog and storefront.')
                                    ->icon('heroicon-o-building-office-2')
                                    ->schema([
                                        AdminUi::translatableTabs('Names', [
                                            'name' => [
                                                'label' => 'Manufacturer Name',
                                                'placeholder' => 'e.g. Bosch, Continental, ZF',
                                                'required' => true,
                                                'maxLength' => 200,
                                                'helperText' => 'English name is required and used as the default fallback.',
                                                'slugSync' => true,
                                            ],
                                        ], slugSyncTarget: 'slug'),
                                        Forms\Components\TextInput::make('slug')
                                            ->label('URL Slug')
                                            ->placeholder('e.g. bosch, continental')
                                            ->helperText('Used in manufacturer page URLs (e.g. /manufacturers/bosch). Auto-filled from the English name.')
                                            ->required()
                                            ->maxLength(200)
                                            ->unique(ignoreRecord: true)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Settings')
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->description('Logo, country, OEM verification, and display settings.')
                                    ->schema([
                                        Forms\Components\Select::make('logo_id')
                                            ->label('Brand Logo')
                                            ->relationship('logo', 'file_name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('Select a media file to use as the manufacturer logo.'),
                                        Forms\Components\Select::make('country_code')
                                            ->label('Country of Origin')
                                            ->options([
                                                'DE' => 'Germany',
                                                'FR' => 'France',
                                                'IT' => 'Italy',
                                                'JP' => 'Japan',
                                                'KR' => 'South Korea',
                                                'SE' => 'Sweden',
                                                'CZ' => 'Czech Republic',
                                                'US' => 'United States',
                                                'GB' => 'United Kingdom',
                                                'ES' => 'Spain',
                                                'NL' => 'Netherlands',
                                                'AT' => 'Austria',
                                                'PL' => 'Poland',
                                            ])
                                            ->searchable()
                                            ->native(false)
                                            ->nullable()
                                            ->helperText('Primary country associated with this manufacturer.'),
                                        Forms\Components\Toggle::make('is_verified_oem')
                                            ->label('Verified OEM Manufacturer')
                                            ->helperText('Shows a verified trust badge on the storefront. Indicates genuine OEM parts supplier.')
                                            ->default(false),
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Manufacturer Active')
                                            ->helperText('Inactive manufacturers are hidden from the storefront and product search.')
                                            ->default(true),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label('Display Order')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Lower numbers appear first in manufacturer listings.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('logo')->withCount('products'))
            ->columns([
                Tables\Columns\ImageColumn::make('logo.file_url')
                    ->label('Logo')
                    ->circular()
                    ->size(40),
                Tables\Columns\TextColumn::make('name')
                    ->label('Manufacturer')
                    ->getStateUsing(fn (Manufacturer $record): string => AdminUi::localizedName($record->name))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('name->en', 'like', "%{$search}%")
                                ->orWhere('name->de', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->description(fn (Manufacturer $record): ?string => $record->slug ?: null)
                    ->limit(28),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->badge()
                    ->placeholder('—')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->fontMono()
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_verified_oem')
                    ->label('OEM')
                    ->badge()
                    ->alignCenter()
                    ->toggleable()
                    ->getStateUsing(fn (Manufacturer $record): string => $record->is_verified_oem ? 'Verified' : 'Standard')
                    ->color(fn (string $state): string => $state === 'Verified' ? 'success' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Verified' ? 'heroicon-o-check-badge' : 'heroicon-o-minus'),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (Manufacturer $record): string => $record->is_active ? 'Active' : 'Inactive')
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
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Manufacturer Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('is_verified_oem')
                    ->label('OEM Verification')
                    ->placeholder('All')
                    ->trueLabel('Verified OEM')
                    ->falseLabel('Not Verified')
                    ->columnSpan(1),
                Tables\Filters\Filter::make('created_at')
                    ->label('Date Added')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Added After')
                            ->placeholder('Select start date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Added Before')
                            ->placeholder('Select end date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q) => $q->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn ($q) => $q->whereDate('created_at', '<=', $data['created_until']));
                    })
                    ->columns(2)
                    ->columnSpan(2),
            ])
            ->filtersFormColumns(2)
            ->actions(AdminUi::recordActions())
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::impactBulkAction(
                        name: 'bulkActivate',
                        label: 'Activate',
                        color: 'success',
                        icon: 'heroicon-o-check-circle',
                        summary: fn ($record): ?array => $record->is_active
                            ? null
                            : [
                                'key' => AdminUi::localizedName($record->name),
                                'old' => 'Inactive',
                                'new' => 'Active',
                            ],
                        action: fn ($records) => $records->each->update(['is_active' => true]),
                    ),
                    AdminUi::impactBulkAction(
                        name: 'bulkDeactivate',
                        label: 'Deactivate',
                        color: 'danger',
                        icon: 'heroicon-o-x-circle',
                        summary: fn ($record): ?array => !$record->is_active
                            ? null
                            : [
                                'key' => AdminUi::localizedName($record->name),
                                'old' => 'Active',
                                'new' => 'Inactive',
                            ],
                        action: fn ($records) => $records->each->update(['is_active' => false]),
                    ),
                    AdminUi::exportCsvBulkAction('Export Manufacturers', [
                        'name' => 'Name',
                        'country_code' => 'Country',
                        'products_count' => 'Products',
                        'is_active' => 'Active',
                        'is_verified_oem' => 'Verified OEM',
                    ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading('No manufacturers added yet')
            ->emptyStateDescription('Add OEM brands to organize your parts catalog and enable manufacturer-based filtering.');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ManufacturerResource\RelationManagers\ProductsRelationManager::class,
            \App\Filament\Resources\ManufacturerResource\RelationManagers\CarModelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListManufacturers::route('/'),
            'create' => Pages\CreateManufacturer::route('/create'),
            'view'   => Pages\ViewManufacturer::route('/{record}'),
            'edit'   => Pages\EditManufacturer::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        // 'name.en' removed — see ProductResource::getGloballySearchableAttributes()
        // and CLAUDE.md FILAMENT rule #26. Filament's dot notation means
        // relationship.column, never JSON-column.key; 'name.en' made every
        // search throw (Manufacturer has no name() relationship).
        return ['slug'];
    }

    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $query->orWhere('name->en', 'like', "%{$search}%");
    }
}
