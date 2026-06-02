<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class CartSettings extends SettingsPage
{
    protected static ?string $title = 'Cart Settings';

    protected static string $settingsGroup = 'cart';

    protected static ?int $navigationSort = 33;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shopping-cart';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cart Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('expiry_days')
                            ->label('Cart Expiry (days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->required()
                            ->default(7),
                        Forms\Components\TextInput::make('max_items')
                            ->label('Max Items per Cart')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500)
                            ->required()
                            ->default(50),
                        Forms\Components\TextInput::make('price_change_threshold')
                            ->label('Price Change Threshold (%)')
                            ->helperText('Percentage change that triggers a price alert')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->default(20),
                        Forms\Components\Toggle::make('otp_required_guest')
                            ->label('Require OTP for Guest Checkout')
                            ->default(true),
                        Forms\Components\TextInput::make('checkout_timeout_minutes')
                            ->label('Checkout Timeout (minutes)')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(120)
                            ->required()
                            ->default(30),
                        Forms\Components\Toggle::make('coupon_enabled')
                            ->label('Enable Coupons')
                            ->default(true),
                        Forms\Components\Toggle::make('merge_on_login')
                            ->label('Merge Guest Cart on Login')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
