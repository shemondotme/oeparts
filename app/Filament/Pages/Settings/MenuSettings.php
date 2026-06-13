<?php

namespace App\Filament\Pages\Settings;

use App\Models\Menu;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class MenuSettings extends SettingsPage
{
    protected static ?string $title = 'Menu Settings';

    protected static string $settingsGroup = 'menu';

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return 'Menus';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-bars-3';
    }

    public function form(Schema $schema): Schema
    {
        $menus = Menu::orderBy('name')->get();

        return $schema
            ->components([
                Section::make('Navigation Menus')
                    ->description('Configure which menus appear in the storefront header and footer. Menu items are managed via the Content > Menus resource.')
                    ->schema([
                        Forms\Components\Repeater::make('menus')
                            ->label('Active Menus')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Menu Name')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('location')
                                    ->label('Location')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('items_count')
                                    ->label('Items')
                                    ->disabled()
                                    ->dehydrated(false),
                            ])
                            ->items(collect($menus)->mapWithKeys(fn ($menu) => [
                                $menu->id => [
                                    'name' => $menu->name,
                                    'location' => $menu->location ?? 'Not set',
                                    'items_count' => $menu->items()->count() . ' items',
                                ],
                            ])->toArray())
                            ->columns(3)
                            ->disabled(),
                    ]),

                Section::make('Footer Links')
                    ->description('Configure footer navigation link behavior.')
                    ->schema([
                        Forms\Components\Toggle::make('footer_show_about')
                            ->label('Show "About Us" in Footer')
                            ->default(true),
                        Forms\Components\Toggle::make('footer_show_contact')
                            ->label('Show "Contact" in Footer')
                            ->default(true),
                        Forms\Components\Toggle::make('footer_show_faq')
                            ->label('Show "FAQ" in Footer')
                            ->default(true),
                        Forms\Components\Toggle::make('footer_show_blog')
                            ->label('Show "Blog" in Footer')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
