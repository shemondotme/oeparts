<?php

namespace App\Filament\Resources;

use App\Enums\ProductCondition;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use App\Services\OemNormalizerService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->schema([
                        Forms\Components\TextInput::make('oem_number')
                            ->label('OEM Number')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Will be auto-normalized on save')
                            ->extraAttributes(['inputmode' => 'text', 'autocapitalize' => 'characters']),
                        Forms\Components\Select::make('manufacturer_id')
                            ->relationship('manufacturer', 'name')
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                            ->required(),
                        Forms\Components\Select::make('condition')
                            ->options(ProductCondition::class)
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label('Price (ex. VAT)')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                    ])->columns(2),

                Section::make('Inventory')
                    ->schema([
                        Forms\Components\TextInput::make('delivery_time')
                            ->label('Delivery Time')
                            ->nullable()
                            ->maxLength(50)
                            ->helperText('e.g. "3-5 days"'),
                        Forms\Components\TextInput::make('moq')
                            ->label('Minimum Order Quantity')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        Forms\Components\Toggle::make('is_in_stock')
                            ->label('In Stock')
                            ->default(true),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),

                Section::make('Names (Multilingual)')
                    ->schema([
                        Forms\Components\KeyValue::make('name')
                            ->label('Product Name')
                            ->keyLabel('Language')
                            ->valueLabel('Name')
                            ->default(['en' => '', 'de' => '', 'lt' => '', 'fr' => '', 'es' => ''])
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Descriptions (Multilingual)')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->helperText('JSON format: {"en": "...", "de": "...", "lt": "...", "fr": "...", "es": "..."}')
                            ->nullable()
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),

                Section::make('Cross-References')
                    ->schema([
                        Forms\Components\Repeater::make('crossReferences')
                            ->relationship('crossReferences')
                            ->schema([
                                Forms\Components\TextInput::make('cross_oem_number')
                                    ->label('Cross OEM Number')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Add cross-reference')
                            ->columnSpanFull(),
                    ]),

                Section::make('Car Models')
                    ->schema([
                        Forms\Components\Select::make('carModels')
                            ->relationship('carModels', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO Meta')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(255)
                            ->nullable()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->maxLength(500)
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('canonical_url')
                            ->label('Canonical URL')
                            ->url()
                            ->maxLength(500)
                            ->nullable()
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('og_title')
                                    ->label('OG Title')
                                    ->maxLength(255)
                                    ->nullable(),
                                Forms\Components\TextInput::make('og_description')
                                    ->label('OG Description')
                                    ->maxLength(500)
                                    ->nullable(),
                                Forms\Components\Select::make('robots')
                                    ->label('Robots')
                                    ->options([
                                        'index,follow' => 'Index & Follow',
                                        'noindex,follow' => 'No Index & Follow',
                                        'index,nofollow' => 'Index & No Follow',
                                        'noindex,nofollow' => 'No Index & No Follow',
                                    ])
                                    ->default('index,follow')
                                    ->nullable(),
                            ]),
                    ])
                    ->relationship('seoMeta')
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('oem_number')
                    ->label('OEM Number')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('OEM number copied')
                    ->extraAttributes(['class' => 'oem-number'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Manufacturer')
                    ->getStateUsing(fn (Product $record): string => $record->manufacturer ? (is_array($record->manufacturer->name) ? ($record->manufacturer->name['en'] ?? $record->manufacturer->name[array_key_first($record->manufacturer->name)] ?? '—') : ($record->manufacturer->name ?? '—')) : '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('manufacturer', function ($q) use ($search) {
                            $q->where('name->en', 'like', "%{$search}%")
                                ->orWhere('name->de', 'like', "%{$search}%");
                        });
                    })
                    ->limit(20),
                Tables\Columns\TextColumn::make('condition')
                    ->label('Condition')
                    ->badge()
                    ->color(fn (ProductCondition $state): string => match ($state) {
                        ProductCondition::New             => 'success',
                        ProductCondition::Used            => 'info',
                        ProductCondition::UsedGradeA      => 'info',
                        ProductCondition::UsedGradeB      => 'warning',
                        ProductCondition::UsedGradeC      => 'gray',
                        ProductCondition::Remanufactured  => 'primary',
                        ProductCondition::Aftermarket     => 'danger',
                        ProductCondition::NewOldStock     => 'info',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->getStateUsing(fn (Product $record): string => format_money($record->price))
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_in_stock')
                    ->label('Stock')
                    ->getStateUsing(fn (Product $record): string => $record->is_in_stock ? 'In Stock' : 'Out of Stock')
                    ->badge()
                    ->color(fn (Product $record): string => $record->is_in_stock ? 'success' : 'danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivery_time')
                    ->label('Delivery')
                    ->placeholder('—')
                    ->limit(15),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('manufacturer_id')
                    ->relationship('manufacturer', 'name')
                    ->label('Manufacturer')
                    ->getOptionLabelFromRecordUsing(fn ($record) => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('condition')
                    ->options(ProductCondition::class)
                    ->multiple(),
                Tables\Filters\SelectFilter::make('is_in_stock')
                    ->label('Stock Status')
                    ->options([
                        '1' => 'In Stock',
                        '0' => 'Out of Stock',
                    ]),
                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\TextInput::make('min_price')
                            ->label('Min Price')
                            ->numeric(),
                        Forms\Components\TextInput::make('max_price')
                            ->label('Max Price')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min_price'], fn ($q, $val) => $q->where('price', '>=', $val))
                            ->when($data['max_price'], fn ($q, $val) => $q->where('price', '<=', $val));
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('toggleStock')
                    ->label(fn (Product $record): string => $record->is_in_stock ? 'Mark Out of Stock' : 'Mark In Stock')
                    ->icon(fn (Product $record): string => $record->is_in_stock ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Product $record): string => $record->is_in_stock ? 'danger' : 'success')
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
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('priceIncrease')
                        ->label('Increase Price %')
                        ->icon('heroicon-o-arrow-trending-up')
                        ->schema([
                            Forms\Components\TextInput::make('percentage')
                                ->label('Percentage Increase')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix('%'),
                        ])
                        ->action(function ($records, array $data): void {
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
                        }),
                    Actions\BulkAction::make('priceDecrease')
                        ->label('Decrease Price %')
                        ->icon('heroicon-o-arrow-trending-down')
                        ->schema([
                            Forms\Components\TextInput::make('percentage')
                                ->label('Percentage Decrease')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix('%'),
                        ])
                        ->action(function ($records, array $data): void {
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
                        }),
                    Actions\BulkAction::make('markInStock')
                        ->label('Mark In Stock')
                        ->icon('heroicon-o-check-circle')
                        ->action(function ($records): void {
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
                        }),
                    Actions\BulkAction::make('markOutOfStock')
                        ->label('Mark Out of Stock')
                        ->icon('heroicon-o-x-circle')
                        ->action(function ($records): void {
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
                        }),
                    Actions\BulkAction::make('exportCsv')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records): void {
                            $csv = "OEM Number,Manufacturer,Condition,Price,Delivery Time,In Stock,Active\n";
                            foreach ($records as $record) {
                                $mfg = $record->manufacturer ? (is_array($record->manufacturer->name) ? ($record->manufacturer->name['en'] ?? '—') : ($record->manufacturer->name ?? '—')) : '—';
                                $csv .= sprintf(
                                    "%s,%s,%s,%s,%s,%s,%s\n",
                                    $record->oem_number,
                                    $mfg,
                                    $record->condition->value,
                                    $record->price,
                                    $record->delivery_time ?? '',
                                    $record->is_in_stock ? 'Yes' : 'No',
                                    $record->is_active ? 'Yes' : 'No'
                                );
                            }

                            $filename = 'products_export_' . now()->format('Y-m-d_His') . '.csv';
                            $path = storage_path('app/public/' . $filename);
                            file_put_contents($path, $csv);

                            Notification::make()
                                ->title('CSV exported')
                                ->body("File: {$filename}")
                                ->success()
                                ->send();
                        }),
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Actions\Action::make('importCsv')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->schema([
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        Notification::make()
                            ->title('CSV import started')
                            ->body('Processing in background')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
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
}
