<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManufacturerResource\Pages;
use App\Models\Manufacturer;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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
        return 20;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Manufacturer Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name (JSON)')
                            ->helperText('JSON format: {"en": "BMW", "de": "BMW", "lt": "BMW", "fr": "BMW", "es": "BMW"}')
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (is_string($state) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(200)
                            ->unique(ignoreRecord: true),
                        Forms\Components\FileUpload::make('logo_id')
                            ->label('Logo')
                            ->image()
                            ->nullable()
                            ->directory('manufacturer-logos'),
                        Forms\Components\Select::make('country_code')
                            ->label('Country')
                            ->options([
                                'DE' => '🇩🇪 Germany',
                                'FR' => '🇫🇷 France',
                                'IT' => '🇮🇹 Italy',
                                'JP' => '🇯🇵 Japan',
                                'KR' => '🇰🇷 South Korea',
                                'SE' => '🇸🇪 Sweden',
                                'CZ' => '🇨🇿 Czech Republic',
                                'US' => '🇺🇸 United States',
                                'GB' => '🇬🇧 United Kingdom',
                                'ES' => '🇪🇸 Spain',
                                'NL' => '🇳🇱 Netherlands',
                                'AT' => '🇦🇹 Austria',
                                'PL' => '🇵🇱 Poland',
                            ])
                            ->nullable(),
                        Forms\Components\Toggle::make('is_verified_oem')
                            ->label('Verified OEM')
                            ->default(false),
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
                Tables\Columns\ImageColumn::make('logo.file_url')
                    ->label('Logo')
                    ->circular()
                    ->size(40),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->getStateUsing(fn (Manufacturer $record): string => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('name->en', 'like', "%{$search}%")
                                ->orWhere('name->de', 'like', "%{$search}%");
                        });
                    })
                    ->limit(25),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->getStateUsing(fn (Manufacturer $record): string => match ($record->country_code) {
                        'DE' => '🇩🇪 DE',
                        'FR' => '🇫🇷 FR',
                        'IT' => '🇮🇹 IT',
                        'JP' => '🇯🇵 JP',
                        'KR' => '🇰🇷 KR',
                        'SE' => '🇸🇪 SE',
                        'CZ' => '🇨🇿 CZ',
                        'US' => '🇺🇸 US',
                        'GB' => '🇬🇧 GB',
                        'ES' => '🇪🇸 ES',
                        'NL' => '🇳🇱 NL',
                        'AT' => '🇦🇹 AT',
                        'PL' => '🇵🇱 PL',
                        default => $record->country_code ?? '—',
                    })
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_verified_oem')
                    ->label('Verified OEM')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TernaryFilter::make('is_verified_oem')
                    ->label('Verified OEM'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ManufacturerResource\RelationManagers\ProductsRelationManager::class,
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

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('is_active', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
