<?php

namespace App\Filament\Resources;

use App\Enums\MenuLocation;
use App\Filament\Resources\MenuResource\Pages;
use App\Filament\Resources\MenuResource\RelationManagers;
use App\Models\Menu;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-bars-3';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Menu Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Menu Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Select::make('location')
                            ->options(MenuLocation::class)
                            ->required(),
                        Forms\Components\Select::make('lang')
                            ->label('Language')
                            ->options([
                                'en' => 'English',
                                'de' => 'German',
                                'lt' => 'Lithuanian',
                                'fr' => 'French',
                                'es' => 'Spanish',
                            ])
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Menu')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->badge()
                    ->color(fn (MenuLocation $state): string => match ($state) {
                        MenuLocation::Header => 'info',
                        MenuLocation::Footer => 'gray',
                    }),
                Tables\Columns\TextColumn::make('lang')
                    ->label('Lang')
                    ->badge()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->options(MenuLocation::class),
                Tables\Filters\SelectFilter::make('lang')
                    ->options([
                        'en' => 'English',
                        'de' => 'German',
                        'lt' => 'Lithuanian',
                        'fr' => 'French',
                        'es' => 'Spanish',
                    ]),
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
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MenuItemRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'view'   => Pages\ViewMenu::route('/{record}'),
            'edit'   => Pages\EditMenu::route('/{record}/edit'),
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
