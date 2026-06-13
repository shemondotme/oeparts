<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingMethodResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Enums\FontWeight;

class ShippingMethodResource extends Resource
{
    protected static ?string $model = ShippingMethod::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-paper-airplane';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Commerce';
    }

    public static function getNavigationSort(): ?int
    {
        return 35;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return null;
    }

    public static function getRecordTitle(?Model $record): string|null
    {
        if (!$record instanceof ShippingMethod) {
            return null;
        }

        return static::localizedName($record->name);
    }

    /**
     * Resolve a translatable JSON field to a display string.
     */
    public static function localizedName(mixed $name, string $fallback = '—'): string
    {
        return AdminUi::localizedName($name, $fallback);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        // ─── Main column ──────────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Localized Content')
                                    ->icon('heroicon-o-language')
                                    ->description('Method names and descriptions displayed per language on the storefront.')
                                    ->schema([
                                        Tabs::make('Locales')
                                            ->schema(
                                                collect(AdminUi::LOCALES)
                                                    ->map(fn (string $label, string $code) => Tab::make($label)
                                                        ->badge($code === 'en' ? 'Primary' : null)
                                                        ->schema([
                                                            Forms\Components\TextInput::make("name.$code")
                                                                ->label('Method Name')
                                                                ->placeholder('e.g. Standard Delivery, Express Shipping')
                                                                ->required($code === 'en')
                                                                ->maxLength(255)
                                                                ->helperText($code === 'en' ? 'English name is required and used as the default fallback.' : null),
                                                            Forms\Components\Textarea::make("description.$code")
                                                                ->label('Description')
                                                                ->placeholder('e.g. Delivered within 3-5 business days via DHL.')
                                                                ->rows(4)
                                                                ->nullable()
                                                                ->helperText('Optional customer-facing description shown during checkout.'),
                                                        ]))
                                                    ->values()
                                                    ->all()
                                            )
                                            ->columnSpanFull(),
                                    ]),
                                Section::make('Delivery & Pricing')
                                    ->icon('heroicon-o-truck')
                                    ->description('Set the shipping zone, cost, and estimated delivery window for this method.')
                                    ->schema([
                                        Forms\Components\Select::make('zone_id')
                                            ->label('Shipping Zone')
                                            ->relationship('zone', 'name')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->getOptionLabelFromRecordUsing(fn (ShippingZone $record): string => (string) $record->name)
                                            ->helperText('The geographic zone this shipping method applies to.'),
                                        Forms\Components\TextInput::make('flat_rate')
                                            ->label('Flat Rate (ex. VAT)')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->prefix('€')
                                            ->placeholder('0.00')
                                            ->helperText('Fixed shipping cost charged per order. VAT is calculated at checkout.'),
                                        Forms\Components\TextInput::make('free_shipping_threshold')
                                            ->label('Free Shipping Threshold (ex. VAT)')
                                            ->numeric()
                                            ->nullable()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->prefix('€')
                                            ->placeholder('e.g. 150.00')
                                            ->helperText('Orders above this amount ship for free. Leave empty to disable free shipping.'),
                                        Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('estimated_days_min')
                                                ->label('Minimum Delivery Days')
                                                ->numeric()
                                                ->required()
                                                ->minValue(1)
                                                ->placeholder('e.g. 3')
                                                ->helperText('Fewest business days for delivery.'),
                                            Forms\Components\TextInput::make('estimated_days_max')
                                                ->label('Maximum Delivery Days')
                                                ->numeric()
                                                ->required()
                                                ->minValue(1)
                                                ->placeholder('e.g. 5')
                                                ->helperText('Most business days for delivery.'),
                                        ]),
                                    ])
                                    ->columns(2),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Visibility & Ordering')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Control whether this method appears at checkout and its display position.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Method Active')
                                            ->helperText('Inactive methods are hidden from the checkout page.')
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
        $locales = array_keys(AdminUi::LOCALES);

        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('zone'))
            ->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Method')
                ->getStateUsing(fn (ShippingMethod $record): string => static::localizedName($record->name))
                ->searchable(query: function (Builder $query, string $search) use ($locales): Builder {
                    return $query->where(function (Builder $q) use ($search, $locales): void {
                        foreach ($locales as $code) {
                            $q->orWhere("name->$code", 'like', "%{$search}%");
                        }
                    });
                })
                ->sortable()
                ->weight(FontWeight::Medium)
                ->limit(28),
            Tables\Columns\TextColumn::make('zone.name')
                ->label('Zone')
                ->getStateUsing(fn (ShippingMethod $record): string => $record->zone?->name ?? '—')
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->whereHas('zone', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                })
                ->toggleable(),
            Tables\Columns\TextColumn::make('flat_rate')
                ->label('Rate')
                ->getStateUsing(fn (ShippingMethod $record): string => format_money($record->flat_rate))
                ->alignEnd()
                ->fontMono()
                ->sortable(),
            Tables\Columns\TextColumn::make('free_shipping_threshold')
                ->label('Free Threshold')
                ->getStateUsing(fn (ShippingMethod $record): string => filled($record->free_shipping_threshold) ? format_money($record->free_shipping_threshold) : '—')
                ->alignEnd()
                ->fontMono()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('estimated_days_min')
                ->label('Delivery')
                ->getStateUsing(fn (ShippingMethod $record): string => $record->estimated_days_min && $record->estimated_days_max
                    ? "{$record->estimated_days_min}–{$record->estimated_days_max} days"
                    : '—')
                ->badge()
                ->color('gray')
                ->alignCenter(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->alignCenter()
                    ->sortable()
                    ->getStateUsing(fn (ShippingMethod $record): string => $record->is_active ? 'Active' : 'Inactive')
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
                    ->label('Method Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
                Tables\Filters\SelectFilter::make('zone_id')
                    ->label('Shipping Zone')
                    ->relationship('zone', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Filter methods by their assigned zone.'),
            ])
            ->actions(AdminUi::recordActions())
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Methods', [
                    'name' => 'Method',
                    'zone.name' => 'Zone',
                    'flat_rate' => 'Rate',
                    'free_shipping_threshold' => 'Free Threshold',
                    'estimated_days_min' => 'Min Days',
                    'estimated_days_max' => 'Max Days',
                    'is_active' => 'Active',
                    'sort_order' => 'Sort Order',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No shipping methods configured')
            ->emptyStateDescription('Add shipping methods to control available delivery options at checkout. Each method must be assigned to a zone.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Method')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListShippingMethods::route('/'),
            'create' => Pages\CreateShippingMethod::route('/create'),
            'view'   => Pages\ViewShippingMethod::route('/{record}'),
            'edit'   => Pages\EditShippingMethod::route('/{record}/edit'),
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

