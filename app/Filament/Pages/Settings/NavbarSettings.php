<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NavbarSettings extends SettingsPage
{
    protected static ?string $title = 'Navbar & Mini-Cart Copy';

    protected static string $settingsGroup = 'navbar';

    protected static ?int $navigationSort = 25;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-bars-3-bottom-left';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Navigation Labels')
                    ->description('Text shown in the storefront navbar. Leave a field empty to use the built-in default.')
                    ->schema([
                        Forms\Components\TextInput::make('account_label')
                            ->label('Account Menu Label')
                            ->placeholder('Account')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('my_account_label')
                            ->label('My Account Link')
                            ->placeholder('My Account')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('sign_in_label')
                            ->label('Sign In Link')
                            ->placeholder('Sign In')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('sign_in_register_label')
                            ->label('Sign In / Register Link')
                            ->placeholder('Sign In / Register')
                            ->maxLength(50),
                    ])->columns(2),
                Section::make('Mini-Cart Labels')
                    ->description('Text inside the dropdown mini-cart.')
                    ->schema([
                        Forms\Components\TextInput::make('cart_label')
                            ->label('Cart Label')
                            ->placeholder('Cart')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('cart_title')
                            ->label('Mini-Cart Title')
                            ->placeholder('Your Cart')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('subtotal_label')
                            ->label('Subtotal Label')
                            ->placeholder('Subtotal')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('view_cart_label')
                            ->label('View Cart Button')
                            ->placeholder('View Cart')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('checkout_label')
                            ->label('Checkout Button')
                            ->placeholder('Checkout')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('remove_label')
                            ->label('Remove Item Label')
                            ->placeholder('Remove')
                            ->maxLength(50),
                    ])->columns(2),
            ]);
    }
}
