<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Filament\Support\AdminUi;
use App\Jobs\ProcessCsvImport;
use App\Models\Condition;
use App\Models\Product;
use App\Services\OemNormalizerService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationAction;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    /**
     * Locales supported for translatable content. Reused by the
     * multilingual form tabs and the view page.
     *
     * @var array<string, string>
     */
    // LOCALES constant is defined in AdminUi::LOCALES

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-cube';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'oem_number';
    }

    /**
     * Condition choices shown on create/edit forms.
     *
     * @return array<string, string>
     */
    public static function conditionFormOptions(): array
    {
        return Condition::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->toArray();
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
                                Section::make('Identification')
                                    ->description('Core identifiers used across search and the storefront.')
                                    ->icon('heroicon-o-identification')
                                    ->schema([
                                        Forms\Components\TextInput::make('oem_number')
                                            ->label('OEM Number')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('e.g. 04L115399F')
                                            ->helperText('Original Equipment Manufacturer part number. Automatically normalized on save.')
                                            ->extraAttributes(['inputmode' => 'text', 'autocapitalize' => 'characters']),
                                        Forms\Components\Select::make('manufacturer_id')
                                            ->label('Manufacturer')
                                            ->relationship('manufacturer', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->getOptionLabelFromRecordUsing(fn ($record) => static::localizedName($record->name))
                                            ->required()
                                            ->helperText('The brand or OEM that manufactures this part.'),
                                    ])
                                    ->columns(2),

                                Section::make('Product Content')
                                    ->description('Names and descriptions shown to customers in each language.')
                                    ->icon('heroicon-o-language')
                                    ->schema([
                                        AdminUi::translatableTabs('Locales', [
                                            'name' => [
                                                'label' => 'Product Name',
                                                'required' => true,
                                                'placeholder' => 'e.g. Turbocharger Assembly',
                                                'helperText' => 'Primary product name displayed on the storefront.',
                                            ],
                                            'description' => [
                                                'label' => 'Description',
                                                'type' => 'textarea',
                                                'rows' => 5,
                                                'placeholder' => 'Detailed product description including specifications, features, and compatibility...',
                                                'helperText' => 'Product description visible to customers. Include key specifications and fitment details.',
                                            ],
                                        ]),
                                    ]),

                                Section::make('Compatibility & Cross-References')
                                    ->description('Vehicles this part fits and equivalent OEM numbers for cross-referencing.')
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->schema([
                                        Forms\Components\Select::make('carModels')
                                            ->label('Compatible Car Models')
                                            ->relationship('carModels', 'name')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull()
                                            ->helperText('Select all vehicle models this part is compatible with.'),
                                        Forms\Components\Repeater::make('crossReferences')
                                            ->label('Cross-References')
                                            ->relationship('crossReferences')
                                            ->schema([
                                                Forms\Components\TextInput::make('cross_oem_number')
                                                    ->label('Cross OEM Number')
                                                    ->required()
                                                    ->maxLength(100)
                                                    ->placeholder('e.g. 53039706AB')
                                                    ->helperText('Alternative OEM number that refers to the same part.')
                                                    ->extraAttributes(['autocapitalize' => 'characters']),
                                            ])
                                            ->defaultItems(0)
                                            ->addActionLabel('Add Cross-Reference')
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('SEO & Meta')
                                    ->description('How this product appears in search engines and when shared on social media.')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->relationship('seoMeta')
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\TextInput::make('meta_title')
                                            ->label('Meta Title')
                                            ->helperText('Page title shown in search results. Aim for 50–60 characters for optimal display.')
                                            ->maxLength(255)
                                            ->placeholder('e.g. Turbocharger 04L115399F | OE Quality')
                                            ->columnSpanFull(),
                                        Forms\Components\Textarea::make('meta_description')
                                            ->label('Meta Description')
                                            ->helperText('Snippet text below the title in search results. Aim for 150–160 characters.')
                                            ->maxLength(500)
                                            ->rows(3)
                                            ->placeholder('Genuine OEM turbocharger assembly for Volkswagen Passat 2.0 TDI...')
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('canonical_url')
                                            ->label('Canonical URL')
                                            ->helperText('Optional. Set only if this product page should point to a different preferred URL for SEO purposes.')
                                            ->url()
                                            ->maxLength(500)
                                            ->columnSpanFull(),
                                        Forms\Components\Select::make('robots')
                                            ->label('Search Engine Indexing')
                                            ->options([
                                                'index,follow'     => 'Index & Follow (default)',
                                                'noindex,follow'   => 'No Index, Follow Links',
                                                'index,nofollow'   => 'Index, Do Not Follow Links',
                                                'noindex,nofollow' => 'No Index, No Follow',
                                            ])
                                            ->default('index,follow')
                                            ->native(false)
                                            ->columnSpanFull()
                                            ->helperText('Control how search engines treat this product page.'),
                                        Forms\Components\TextInput::make('og_title')
                                            ->label('Social Share Title')
                                            ->helperText('Title shown when the product link is shared on social media. Leave blank to use the meta title.')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Forms\Components\Textarea::make('og_description')
                                            ->label('Social Share Description')
                                            ->helperText('Preview text on Facebook, LinkedIn, etc. Leave blank to use the meta description.')
                                            ->maxLength(500)
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        Forms\Components\Select::make('og_image_id')
                                            ->label('Social Share Image')
                                            ->helperText('Recommended size: 1200×630 px. This image appears when the product is shared on social platforms.')
                                            ->relationship('ogImage', 'file_name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Pricing')
                                    ->icon('heroicon-o-currency-euro')
                                    ->description('Product pricing and condition classification.')
                                    ->schema([
                                        Forms\Components\TextInput::make('price')
                                            ->label('Price (ex. VAT)')
                                            ->numeric()
                                            ->prefix('€')
                                            ->required()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->placeholder('0.00')
                                            ->helperText('Net selling price excluding VAT. VAT is calculated at checkout.'),
                                        Forms\Components\Select::make('condition_id')
                                            ->label('Condition')
                                            ->relationship('condition', 'name')
                                            ->options(static::conditionFormOptions())
                                            ->native(false)
                                            ->searchable()
                                            ->required()
                                            ->helperText('Select the part condition (New, Used, or custom).'),
                                    ]),

                                Section::make('Availability')
                                    ->icon('heroicon-o-cube')
                                    ->description('Stock status, visibility, and fulfillment details.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Active')
                                            ->helperText('Inactive products are hidden from the storefront and search results.'),
                                        Forms\Components\Toggle::make('is_in_stock')
                                            ->label('In Stock')
                                            ->helperText('Binary stock indicator. Enabled = available for immediate dispatch.'),
                                        Forms\Components\TextInput::make('moq')
                                            ->label('Minimum Order Quantity')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required()
                                            ->helperText('Smallest quantity a customer can order for this part.'),
                                        Forms\Components\TextInput::make('delivery_time')
                                            ->label('Estimated Delivery Time')
                                            ->maxLength(50)
                                            ->placeholder('e.g. 3-5 business days')
                                            ->helperText('Estimated days from order placement to delivery. Shown on the product page.'),
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
                AdminUi::oemColumn('oem_number', 'OEM number copied')
                    ->description(fn (Product $record): ?string => static::localizedName($record->name) ?: null),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Manufacturer')
                    ->getStateUsing(fn (Product $record): string => $record->manufacturer ? static::localizedName($record->manufacturer->name) : '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('manufacturer', function ($q) use ($search) {
                            $q->where('name->en', 'like', "%{$search}%")
                                ->orWhere('name->de', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable()
                    ->limit(24),
                Tables\Columns\TextColumn::make('condition.name')
                    ->label('Condition')
                    ->badge()
                    ->formatStateUsing(fn (?Condition $state): string => $state?->name ?? '—')
                    ->extraAttributes(fn (Product $record): array => $record->condition ? [
                        'style' => "background-color: {$record->condition->bg_color} !important; color: {$record->condition->text_color} !important;",
                    ] : [])
                    ->sortable(),
                Tables\Columns\TextInputColumn::make('price')
                    ->label('Price')
                    ->type('number')
                    ->step(0.01)
                    ->prefix('€')
                    ->rules(['required', 'numeric', 'min:0', 'decimal:0,2'])
                    ->disabled(fn (Product $record): bool => ! auth('admin')->user()?->can('update', $record))
                    ->afterStateUpdated(function (Product $record): void {
                        Notification::make()
                            ->title('Price updated')
                            ->success()
                            ->send();
                    })
                    ->alignEnd()
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('delivery_time')
                    ->label('Delivery')
                    ->placeholder('—')
                    ->toggleable()
                    ->limit(15),
                Tables\Columns\ToggleColumn::make('is_in_stock')
                    ->label('Stock')
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip(fn (Product $record): string => $record->is_in_stock ? 'In stock' : 'Out of stock')
                    ->alignCenter()
                    ->afterStateUpdated(function (\Filament\Tables\Table $table, $record, $state): void {
                        Notification::make()
                            ->title($state ? 'Marked as in stock' : 'Marked as out of stock')
                            ->success()
                            ->send();
                    }),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->alignCenter()
                    ->toggleable()
                    ->getStateUsing(fn (Product $record): string => $record->is_active ? 'Active' : 'Inactive')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('manufacturer_id')
                    ->relationship('manufacturer', 'name')
                    ->label('Manufacturer')
                    ->getOptionLabelFromRecordUsing(fn ($record) => static::localizedName($record->name))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('Filter products by brand or OEM.')
                    ->columnSpan(1),
                Tables\Filters\SelectFilter::make('condition_id')
                    ->label('Part Condition')
                    ->relationship('condition', 'name')
                    ->multiple()
                    ->preload()
                    ->native(false)
                    ->helperText('Filter by one or more condition types.')
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('is_in_stock')
                    ->label('Stock Status')
                    ->placeholder('All')
                    ->trueLabel('In Stock')
                    ->falseLabel('Out of Stock')
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Visibility')
                    ->placeholder('All')
                    ->trueLabel('Active (Visible)')
                    ->falseLabel('Inactive (Hidden)')
                    ->columnSpan(1),
                Tables\Filters\Filter::make('price_range')
                    ->label('Price Range')
                    ->form([
                        Forms\Components\TextInput::make('min_price')
                            ->label('Minimum Price (€)')
                            ->numeric()
                            ->placeholder('0.00'),
                        Forms\Components\TextInput::make('max_price')
                            ->label('Maximum Price (€)')
                            ->numeric()
                            ->placeholder('999.99'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min_price'], fn ($q, $val) => $q->where('price', '>=', $val))
                            ->when($data['max_price'], fn ($q, $val) => $q->where('price', '<=', $val));
                    })
                    ->columns(2)
                    ->columnSpan(2),
            ])
            ->filtersFormColumns(2)
            ->actions(AdminUi::recordActions([], [
                Actions\Action::make('copyOem')
                    ->label('Copy OEM')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->action(function (Product $record): void {
                        $oem = $record->oem_number;
                        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                            $_SERVER['HTTP_X_REQUESTED_WITH'] = '';
                        }
                        Notification::make()
                            ->title("OEM number copied: {$oem}")
                            ->success()
                            ->send();
                    })
                    ->extraAttributes(['x-on:click' => 'navigator.clipboard.writeText($el.dataset.oem); $dispatch(\'toast\', { type: \'success\', message: \'OEM number copied!\' })'])
                    ->record(fn (Product $record): array => ['oem' => $record->oem_number]),
                Actions\Action::make('toggleStock')
                    ->label(fn (Product $record): string => $record->is_in_stock ? 'Mark Out of Stock' : 'Mark In Stock')
                    ->icon(fn (Product $record): string => $record->is_in_stock ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Product $record): string => $record->is_in_stock ? 'danger' : 'success')
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->action(function (Product $record): void {
                        $record->is_in_stock = !$record->is_in_stock;
                        $record->save();

                        Cache::forget('sections.homepage');

                        Notification::make()
                            ->title('Stock status updated')
                            ->success()
                            ->send();
                    }),
            ]))
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::impactBulkAction(
                        name: 'priceIncrease',
                        label: 'Increase Price by %',
                        color: 'warning',
                        icon: 'heroicon-o-arrow-trending-up',
                        form: [
                            Forms\Components\TextInput::make('percentage')
                                ->label('Percentage Increase')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix('%')
                                ->placeholder('e.g. 10')
                                ->helperText('Enter a value between 1 and 100.'),
                        ],
                        action: function ($records, array $data): void {
                            $pct = $data['percentage'];
                            $count = 0;

                            foreach ($records as $record) {
                                $current = $record->price;
                                $increase = bcmul($current, bcdiv((string) $pct, '100', 4), 2);
                                $record->price = bcadd($current, $increase, 2);
                                $record->save();
                                $count++;
                            }

                            Notification::make()
                                ->title("Price increased by {$pct}%")
                                ->body("{$count} products updated")
                                ->success()
                                ->send();
                        },
                    ),
                    AdminUi::impactBulkAction(
                        name: 'priceDecrease',
                        label: 'Decrease Price by %',
                        color: 'warning',
                        icon: 'heroicon-o-arrow-trending-down',
                        form: [
                            Forms\Components\TextInput::make('percentage')
                                ->label('Percentage Decrease')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix('%')
                                ->placeholder('e.g. 10')
                                ->helperText('Enter a value between 1 and 100.'),
                        ],
                        action: function ($records, array $data): void {
                            $pct = $data['percentage'];
                            $count = 0;

                            foreach ($records as $record) {
                                $current = $record->price;
                                $decrease = bcmul($current, bcdiv((string) $pct, '100', 4), 2);
                                $record->price = bcsub($current, $decrease, 2);
                                $record->save();
                                $count++;
                            }

                            Notification::make()
                                ->title("Price decreased by {$pct}%")
                                ->body("{$count} products updated")
                                ->success()
                                ->send();
                        },
                    ),
                    AdminUi::impactBulkAction(
                        name: 'markInStock',
                        label: 'Mark In Stock',
                        color: 'success',
                        icon: 'heroicon-o-check-circle',
                        summary: fn ($record): ?array => $record->is_in_stock
                            ? null
                            : [
                                'key' => $record->oem_number,
                                'old' => 'Out of stock',
                                'new' => 'In stock',
                            ],
                        action: function ($records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if (!$record->is_in_stock) {
                                    $record->is_in_stock = true;
                                    $record->save();
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} products marked in stock")
                                ->success()
                                ->send();
                        },
                    ),
                    AdminUi::impactBulkAction(
                        name: 'markOutOfStock',
                        label: 'Mark Out of Stock',
                        color: 'danger',
                        icon: 'heroicon-o-x-circle',
                        summary: fn ($record): ?array => !$record->is_in_stock
                            ? null
                            : [
                                'key' => $record->oem_number,
                                'old' => 'In stock',
                                'new' => 'Out of stock',
                            ],
                        action: function ($records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->is_in_stock) {
                                    $record->is_in_stock = false;
                                    $record->save();
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} products marked out of stock")
                                ->success()
                                ->send();
                        },
                    ),
                    AdminUi::exportCsvBulkAction('Export Products', [
                        'oem_number' => 'OEM Number',
                        'manufacturer.name' => 'Manufacturer',
                        'condition.name' => 'Condition',
                        'price' => 'Price',
                        'delivery_time' => 'Delivery Time',
                        'is_in_stock' => 'In Stock',
                        'is_active' => 'Active',
                    ]),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                AdminUi::importCsvHeaderAction(
                    ProcessCsvImport::class,
                    'Import Products via CSV',
                    'Upload a CSV file to bulk import or update products. A background job will process the file asynchronously.',
                    'Upload a CSV with columns: oem_number, manufacturer, price, condition, etc.',
                )->authorize(fn (): bool => auth('admin')->user()?->can('import products') ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Create your first product or import a CSV to populate the catalog.')
            ->emptyStateActions([
                Actions\Action::make('create')
                    ->label('Add Product')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
                Actions\Action::make('import')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->outlined(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CrossReferencesRelationManager::class,
            RelationManagers\CarModelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view'   => Pages\ViewProduct::route('/{record}'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_in_stock', false)->where('is_active', true)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::where('is_in_stock', false)->where('is_active', true)->count();

        return $count > 0 ? 'danger' : null;
    }

    /**
     * Resolve a translatable name array (or plain string) to a display string.
     */
    public static function localizedName(mixed $name): string
    {
        return AdminUi::localizedName($name);
    }

    public static function getGloballySearchableAttributes(): array
    {
        // NOTE: 'name.en' was previously listed here, but Filament's dot
        // notation always means "relationship.column" (e.g. manufacturer.name
        // below), never "JSON-column.key". Product has no name() relationship,
        // so that entry made every global search throw a BadMethodCallException
        // ("Call to undefined method Product::name()"). Product name is matched
        // via the JSON-aware orWhere in modifyGlobalSearchQuery() below instead.
        return ['oem_number', 'manufacturer.name'];
    }

    /**
     * Also match on the JSON `name->en` column and on normalized_oem, so
     * admins can find a part by its English name or by an OEM number typed
     * with dashes/spaces/dots (e.g. "06L-906-036-L") — the same way the
     * storefront search always matches on normalized_oem.
     */
    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $query->orWhere('name->en', 'like', "%{$search}%");

        $normalized = app(OemNormalizerService::class)->normalize($search);

        if ($normalized !== '') {
            $query->orWhere('normalized_oem', 'like', "%{$normalized}%");
        }
    }
}
