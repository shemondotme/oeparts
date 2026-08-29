<?php

namespace App\Filament\Resources;

use App\Filament\Pages\Catalog\ProductImport;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Filament\Support\AdminUi;
use App\Models\Condition;
use App\Models\Manufacturer;
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

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

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
                                            ->label(__('admin.oem_number'))
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('e.g. 04L115399F')
                                            ->helperText('Original Equipment Manufacturer part number. Automatically normalized on save.')
                                            ->extraAttributes(['inputmode' => 'text', 'autocapitalize' => 'characters']),
                                        Forms\Components\Select::make('manufacturer_id')
                                            ->label(__('admin.manufacturer'))
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

                                Section::make('Product Images')
                                    ->description('Gallery shown on the storefront and used in Product structured data. The first (or explicitly featured) image is the main one; only one image can be featured at a time.')
                                    ->icon('heroicon-o-photo')
                                    ->schema([
                                        Forms\Components\Repeater::make('images')
                                            ->label('')
                                            ->relationship('images')
                                            ->schema([
                                                Forms\Components\FileUpload::make('path')
                                                    ->label('Image')
                                                    ->image()
                                                    ->disk('public')
                                                    ->directory('product-images')
                                                    ->maxSize(4096)
                                                    ->required()
                                                    ->columnSpan(1)
                                                    ->helperText('Max 4MB. A thumbnail and medium-size version are generated automatically.'),
                                                Forms\Components\Toggle::make('is_featured')
                                                    ->label('Featured (main product image)')
                                                    ->helperText('Only one image per product can be featured — setting this un-sets any other featured image.')
                                                    ->columnSpan(1),
                                                Forms\Components\TextInput::make('image_url')
                                                    ->label('Or fetch from an image URL')
                                                    ->url()
                                                    ->live(onBlur: true)
                                                    ->dehydrated(false)
                                                    ->columnSpanFull()
                                                    ->helperText('For scraped products where only the image link was captured, not the file itself. Paste the link and leave the field — it is fetched and stored automatically.')
                                                    ->afterStateUpdated(function (?string $state, callable $set): void {
                                                        if (blank($state)) {
                                                            return;
                                                        }

                                                        try {
                                                            $path = app(\App\Services\RemoteImageDownloadService::class)->downloadToProductImages($state);
                                                            $set('path', $path);
                                                            $set('image_url', null);
                                                        } catch (\InvalidArgumentException $e) {
                                                            Notification::make()
                                                                ->title('Could not fetch that image')
                                                                ->body($e->getMessage())
                                                                ->danger()
                                                                ->send();
                                                        }
                                                    }),
                                                AdminUi::translatableTabs('Alt Text', [
                                                    'alt_text' => [
                                                        'label' => 'Alt Text',
                                                        'required' => false,
                                                        'helperText' => 'Describes the image for accessibility and image search — e.g. "Bosch brake pad, front, new".',
                                                    ],
                                                ]),
                                            ])
                                            ->columns(2)
                                            ->orderColumn('sort_order')
                                            ->reorderable()
                                            ->collapsible()
                                            ->collapsed(fn (?array $state): bool => filled($state) && count($state) > 1)
                                            ->itemLabel(fn (array $state): ?string => $state['is_featured'] ?? false ? 'Featured image' : 'Gallery image')
                                            ->defaultItems(0)
                                            ->addActionLabel('Add Image')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Compatibility & Cross-References')
                                    ->description('Vehicles this part fits and equivalent OEM numbers for cross-referencing.')
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->schema([
                                        Forms\Components\Select::make('carModels')
                                            ->label(__('admin.compatible_car_models'))
                                            ->relationship('carModels', 'name')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->columnSpanFull()
                                            ->helperText('Select all vehicle models this part is compatible with.'),
                                        Forms\Components\Repeater::make('crossReferences')
                                            ->label(__('admin.cross_references'))
                                            ->relationship('crossReferences')
                                            ->schema([
                                                Forms\Components\TextInput::make('cross_oem_number')
                                                    ->label(__('admin.cross_oem_number'))
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

                                Section::make('Specifications & Media')
                                    ->description('Optional detail-page content — specs table, warranty period, and product video each only render on the storefront when set.')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\KeyValue::make('specifications')
                                            ->label('Specifications')
                                            ->keyLabel('Spec name')
                                            ->valueLabel('Value')
                                            ->addActionLabel('Add Specification')
                                            ->helperText('e.g. "Weight" → "2.4 kg", "Material" → "Cast aluminium". Shown as a table on the product page.')
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('warranty_months')
                                            ->label('Warranty (Months)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(360)
                                            ->placeholder('e.g. 12')
                                            ->helperText('Leave blank to hide the warranty block on this product.'),
                                        Forms\Components\TextInput::make('video_url')
                                            ->label('Product Video URL')
                                            ->url()
                                            ->maxLength(500)
                                            ->placeholder('https://example.com/videos/install-guide.mp4')
                                            ->helperText('Direct link to a video file, embedded on the product page.'),
                                    ])
                                    ->columns(2),

                                Section::make('SEO & Meta')
                                    ->description('How this product appears in search engines and when shared on social media.')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->relationship('seoMeta')
                                    ->collapsible()
                                    ->schema([
                                        Forms\Components\TextInput::make('meta_title')
                                            ->label(__('admin.meta_title'))
                                            ->helperText('Page title shown in search results. Aim for 50–60 characters for optimal display.')
                                            ->maxLength(255)
                                            ->placeholder('e.g. Turbocharger 04L115399F | OE Quality')
                                            ->columnSpanFull(),
                                        Forms\Components\Textarea::make('meta_description')
                                            ->label(__('admin.meta_description'))
                                            ->helperText('Snippet text below the title in search results. Aim for 150–160 characters.')
                                            ->maxLength(500)
                                            ->rows(3)
                                            ->placeholder('Genuine OEM turbocharger assembly for Volkswagen Passat 2.0 TDI...')
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('canonical_url')
                                            ->label(__('admin.canonical_url'))
                                            ->helperText('Optional. Set only if this product page should point to a different preferred URL for SEO purposes.')
                                            ->url()
                                            ->maxLength(500)
                                            ->columnSpanFull(),
                                        Forms\Components\Select::make('robots')
                                            ->label(__('admin.search_engine_indexing'))
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
                                            ->label(__('admin.social_share_title'))
                                            ->helperText('Title shown when the product link is shared on social media. Leave blank to use the meta title.')
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Forms\Components\Textarea::make('og_description')
                                            ->label(__('admin.social_share_description'))
                                            ->helperText('Preview text on Facebook, LinkedIn, etc. Leave blank to use the meta description.')
                                            ->maxLength(500)
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        Forms\Components\Select::make('og_image_id')
                                            ->label(__('admin.social_share_image'))
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
                                            ->label(__('admin.price_ex_vat'))
                                            ->numeric()
                                            ->prefix('€')
                                            ->required()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->placeholder('0.00')
                                            ->helperText('Net selling price excluding VAT. VAT is calculated at checkout.'),
                                        Forms\Components\Select::make('condition_id')
                                            ->label(__('admin.condition'))
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
                                            ->label(__('admin.active'))
                                            ->helperText('Inactive products are hidden from the storefront and search results.'),
                                        Forms\Components\Toggle::make('is_in_stock')
                                            ->label(__('admin.in_stock'))
                                            ->helperText('Binary stock indicator. Enabled = available for immediate dispatch.'),
                                        Forms\Components\TextInput::make('moq')
                                            ->label(__('admin.minimum_order_quantity'))
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required()
                                            ->helperText('Smallest quantity a customer can order for this part.'),
                                        Forms\Components\TextInput::make('delivery_time')
                                            ->label(__('admin.estimated_delivery_time'))
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
            ->modifyQueryUsing(fn ($query) => $query->with(['manufacturer', 'condition']))
            ->columns([
                AdminUi::oemColumn('oem_number', 'OEM number copied')
                    ->description(fn (Product $record): ?string => static::localizedName($record->name) ?: null)
                    // Rule #12: OEM search always on normalized_oem, matching
                    // dashes/spaces/dots the way global search and the storefront do.
                    // Product::scopeOemContains() — a leading-wildcard LIKE here
                    // can't use a plain BTREE index; at ~100k products this ran a
                    // full table scan on every search-box keystroke.
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $normalized = app(OemNormalizerService::class)->normalize($search);

                        return $query->oemContains($normalized);
                    }),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label(__('admin.manufacturer'))
                    ->getStateUsing(fn (Product $record): string => $record->manufacturer ? static::localizedName($record->manufacturer->name) : '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('manufacturer', function ($q) use ($search) {
                            foreach (array_keys(AdminUi::LOCALES) as $i => $code) {
                                $q->{$i === 0 ? 'where' : 'orWhere'}("name->{$code}", 'like', "%{$search}%");
                            }
                        });
                    })
                    ->toggleable()
                    ->limit(24),
                Tables\Columns\TextColumn::make('condition.name')
                    ->label(__('admin.condition'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => static::localizedName($state))
                    ->color(fn (Product $record) => $record->condition?->bg_color
                        ? \Filament\Support\Colors\Color::hex($record->condition->bg_color)
                        : 'gray')
                    ->sortable(),
                Tables\Columns\TextInputColumn::make('price')
                    ->label(__('admin.price'))
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
                    ->label(__('admin.delivery'))
                    ->placeholder('—')
                    ->toggleable()
                    ->limit(15),
                Tables\Columns\ToggleColumn::make('is_in_stock')
                    ->label(__('admin.stock'))
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip(fn (Product $record): string => $record->is_in_stock ? 'In stock' : 'Out of stock')
                    // Server-enforced (updateColumnState no-ops on disabled
                    // columns) — same policy guard as the inline price column.
                    ->disabled(fn (Product $record): bool => ! auth('admin')->user()?->can('update', $record))
                    ->alignCenter()
                    ->afterStateUpdated(function (\Filament\Tables\Table $table, $record, $state): void {
                        Notification::make()
                            ->title($state ? 'Marked as in stock' : 'Marked as out of stock')
                            ->success()
                            ->send();
                    }),
                Tables\Columns\TextColumn::make('is_active')
                    ->label(__('admin.active'))
                    ->badge()
                    ->alignCenter()
                    ->toggleable()
                    ->getStateUsing(fn (Product $record): string => $record->is_active ? 'Active' : 'Inactive')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Active' ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.created'))
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('manufacturer_id')
                    ->relationship('manufacturer', 'name')
                    ->label(__('admin.manufacturer'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => static::localizedName($record->name))
                    ->indicateUsing(function (array $state): array {
                        if (blank($state['value'] ?? null)) {
                            return [];
                        }

                        $manufacturer = Manufacturer::find($state['value']);

                        if (! $manufacturer) {
                            return [];
                        }

                        return [Tables\Filters\Indicator::make(__('admin.manufacturer').': '.static::localizedName($manufacturer->name))];
                    })
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('Filter products by brand or OEM.')
                    ->columnSpan(1),
                Tables\Filters\SelectFilter::make('condition_id')
                    ->label(__('admin.part_condition'))
                    ->relationship('condition', 'name')
                    ->multiple()
                    ->preload()
                    ->native(false)
                    ->helperText('Filter by one or more condition types.')
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('is_in_stock')
                    ->label(__('admin.stock_status'))
                    ->placeholder('All')
                    ->trueLabel('In Stock')
                    ->falseLabel('Out of Stock')
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.visibility'))
                    ->placeholder('All')
                    ->trueLabel('Active (Visible)')
                    ->falseLabel('Inactive (Hidden)')
                    ->columnSpan(1),
                Tables\Filters\Filter::make('price_range')
                    ->label(__('admin.price_range'))
                    ->form([
                        Forms\Components\TextInput::make('min_price')
                            ->label(__('admin.minimum_price'))
                            ->numeric()
                            ->placeholder('0.00'),
                        Forms\Components\TextInput::make('max_price')
                            ->label(__('admin.maximum_price'))
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
                AdminUi::trashedFilter(),
            ])
            ->filtersFormColumns(2)
            // Copy-OEM and stock-toggle row actions removed: the OEM column is
            // click-to-copy and the Stock column is an inline toggle already.
            // recordActionsWithTrash (not recordActions): Product uses
            // SoftDeletes, but nothing previously exposed a restore path — a
            // deleted product's row (and its historical OrderItem/CartItem
            // references) survived in the DB with no way back for even a
            // super_admin.
            ->actions(AdminUi::recordActionsWithTrash())
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::impactBulkAction(
                        name: 'priceIncrease',
                        label: 'Increase Price by %',
                        color: 'warning',
                        icon: 'heroicon-o-arrow-trending-up',
                        form: [
                            Forms\Components\TextInput::make('percentage')
                                ->label(__('admin.percentage_increase'))
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
                                ->label(__('admin.percentage_decrease'))
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
                    AdminUi::impactBulkAction(
                        name: 'bulkGenerateSeoMeta',
                        label: 'Bulk-generate SEO meta',
                        color: 'gray',
                        icon: 'heroicon-o-document-text',
                        form: [
                            Forms\Components\TextInput::make('title_template')
                                ->label('Meta Title Template')
                                ->maxLength(255)
                                ->helperText('Placeholders: {oem}, {brand}, {manufacturer}, {name}, {min}, {max}, {site}. Leave blank to skip updating the title.'),
                            Forms\Components\Textarea::make('description_template')
                                ->label('Meta Description Template')
                                ->rows(2)
                                ->maxLength(500)
                                ->helperText('Same placeholders as above. Leave blank to skip updating the description.'),
                            Forms\Components\Toggle::make('overwrite_existing')
                                ->label('Overwrite existing custom SEO meta')
                                ->helperText('When off (default), a product that already has a manually-set title or description is skipped, not overwritten.')
                                ->default(false),
                        ],
                        action: function ($records, array $data): void {
                            $titleTemplate = trim((string) ($data['title_template'] ?? ''));
                            $descriptionTemplate = trim((string) ($data['description_template'] ?? ''));

                            if ($titleTemplate === '' && $descriptionTemplate === '') {
                                Notification::make()->title('Enter at least a title or description template')->warning()->send();

                                return;
                            }

                            \App\Jobs\BulkGenerateProductSeoMeta::dispatch(
                                $records->pluck('id')->all(),
                                $titleTemplate !== '' ? $titleTemplate : null,
                                $descriptionTemplate !== '' ? $descriptionTemplate : null,
                                (bool) ($data['overwrite_existing'] ?? false),
                                auth('admin')->user()?->name ?? 'An admin'
                            );

                            Notification::make()
                                ->title('SEO meta generation queued for '.$records->count().' product(s)')
                                ->body("You'll get a notification here (the bell icon) once it finishes.")
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
                Actions\Action::make('importCsv')
                    ->label(__('admin.import_csv'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->url(fn (): string => ProductImport::getUrl())
                    ->authorize(fn (): bool => auth('admin')->user()?->can('import products') ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Create your first product or import a CSV to populate the catalog.')
            ->emptyStateActions([
                Actions\Action::make('create')
                    ->label(__('admin.add_product'))
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
                Actions\Action::make('importCsvEmpty')
                    ->label(__('admin.import_csv'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->url(fn (): string => ProductImport::getUrl())
                    ->outlined()
                    ->authorize(fn (): bool => auth('admin')->user()?->can('import products') ?? false),
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
        return \App\Support\NavBadge::count('products_oos', fn () => static::getModel()::where('is_in_stock', false)->where('is_active', true)->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Products out of stock';
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
            // Product::scopeOemContains() — a leading-wildcard LIKE here
            // can't use the normalized_oem index; at ~100k products this
            // ran a full table scan on every keystroke of a global search.
            $query->orWhere(fn (Builder $q) => $q->oemContains($normalized));
        }
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'OEM' => $record->oem_number,
            'Stock' => $record->is_in_stock ? 'In stock' : 'Out of stock',
        ];
    }
}
