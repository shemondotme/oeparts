<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarModelResource\Pages;
use App\Filament\Resources\CarModelResource\RelationManagers;
use App\Models\CarModel;
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

class CarModelResource extends Resource
{
    protected static ?string $model = CarModel::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-truck';
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
        $years = range(1970, 2026);
        $yearOptions = array_combine($years, $years);

        return $schema
            ->components([
                Section::make('Car Model Details')
                    ->schema([
                        Forms\Components\Select::make('manufacturer_id')
                            ->relationship('manufacturer', 'name')
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('Model Name')
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(200)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('year_from')
                            ->label('Year From')
                            ->options($yearOptions)
                            ->nullable()
                            ->searchable(),
                        Forms\Components\Select::make('year_to')
                            ->label('Year To')
                            ->options($yearOptions)
                            ->nullable()
                            ->searchable()
                            ->rule(function (callable $get) {
                                return function (string $attribute, $value, callable $fail) use ($get) {
                                    $yearFrom = $get('year_from');
                                    if ($yearFrom && $value && (int) $value < (int) $yearFrom) {
                                        $fail('Year To must be greater than or equal to Year From.');
                                    }
                                };
                            }),
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
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Manufacturer')
                    ->getStateUsing(fn (CarModel $record): string => $record->manufacturer ? (is_array($record->manufacturer->name) ? ($record->manufacturer->name['en'] ?? $record->manufacturer->name[array_key_first($record->manufacturer->name)] ?? '—') : ($record->manufacturer->name ?? '—')) : '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('manufacturer', function ($q) use ($search) {
                            $q->where('name->en', 'like', "%{$search}%");
                        });
                    })
                    ->limit(20),
                Tables\Columns\TextColumn::make('name')
                    ->label('Model')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('year_from')
                    ->label('Year From')
                    ->placeholder('—')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('year_to')
                    ->label('Year To')
                    ->placeholder('—')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
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
                Tables\Filters\SelectFilter::make('manufacturer_id')
                    ->relationship('manufacturer', 'name')
                    ->label('Manufacturer')
                    ->getOptionLabelFromRecordUsing(fn ($record) => is_array($record->name) ? ($record->name['en'] ?? $record->name[array_key_first($record->name)] ?? '—') : ($record->name ?? '—'))
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                $record->is_active = true;
                                $record->save();
                            }

                            Notification::make()
                                ->title('Models activated')
                                ->success()
                                ->send();
                        }),
                    Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                $record->is_active = false;
                                $record->save();
                            }

                            Notification::make()
                                ->title('Models deactivated')
                                ->success()
                                ->send();
                        }),
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
}
