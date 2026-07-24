<?php

namespace App\Filament\Resources;

use App\Enums\MenuLocation;
use App\Filament\Resources\MenuResource\Pages;
use App\Filament\Resources\MenuResource\RelationManagers;
use App\Filament\Support\AdminUi;
use App\Models\Menu;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-bars-3';
    }

    protected static ?string $cluster = \App\Filament\Clusters\Content::class;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return 55;
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
                        // ─── Main column ──────────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Menu Details')
                                    ->icon('heroicon-o-bars-3')
                                    ->description('Define the menu name, location, and language for navigation.')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label(__('admin.menu_name'))
                                            ->placeholder('e.g. Main Navigation, Footer Links')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('Internal name for this menu. Not shown to customers.'),
                                        Forms\Components\Select::make('location')
                                            ->label(__('admin.menu_location'))
                                            ->options(MenuLocation::class)
                                            ->native(false)
                                            ->required()
                                            ->helperText('Where this menu appears on the storefront (header or footer).'),
                                        Forms\Components\Select::make('lang')
                                            ->label(__('admin.language'))
                                            ->options(AdminUi::LOCALES)
                                            ->native(false)
                                            ->required()
                                            ->helperText('The language this menu is displayed in.'),
                                    ])->columns(2),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Settings')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Menu visibility settings.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('admin.menu_active'))
                                            ->helperText('Inactive menus are hidden from the storefront navigation.')
                                            ->default(true),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('items'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.menu'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
            Tables\Columns\TextColumn::make('location')
                ->label(__('admin.location'))
                ->badge()
                ->color(fn (MenuLocation $state): string => match ($state) {
                    MenuLocation::Header => 'info',
                    MenuLocation::Footer => 'gray',
                }),
                Tables\Columns\TextColumn::make('lang')
                    ->label(__('admin.language'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminUi::LOCALES[$state] ?? strtoupper($state))
                    ->color('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('admin.menu_items'))
                    ->counts('items')
                    ->fontMono()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.active'))
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->label(__('admin.menu_location'))
                    ->options(MenuLocation::class)
                    ->helperText('Filter by header or footer menus.'),
                Tables\Filters\SelectFilter::make('lang')
                    ->label(__('admin.language'))
                    ->options(AdminUi::LOCALES)
                    ->helperText('Filter menus by language.'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.menu_status'))
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions(AdminUi::recordActions())
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Menus', [
                    'name' => 'Menu',
                    'location' => 'Location',
                    'lang' => 'Language',
                    'items_count' => 'Items',
                    'is_active' => 'Active',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
        ])
            ->defaultSort('name', 'asc')
            ->emptyStateIcon('heroicon-o-bars-3')
            ->emptyStateHeading('No navigation menus created yet')
            ->emptyStateDescription('Create navigation menus for the header or footer, then add menu items to build the navigation structure.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label(__('admin.create_menu'))
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}

