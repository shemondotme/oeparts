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
                Section::make('Cart & Checkout Operations')
                    ->description('Set rules for guest/portal cart timeouts, price discrepancy warnings, and merge settings.')
                    ->schema([
                        Forms\Components\TextInput::make('expiry_days')
                            ->label('Inactive Cart Lifespan (Days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->required()
                            ->helperText('Number of days to preserve abandoned items before clearing from database')
                            ->default(7),

                        Forms\Components\TextInput::make('max_items')
                            ->label('Maximum Unique Items per Cart')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500)
                            ->required()
                            ->helperText('Prevents excessive load and protects checkout routes')
                            ->default(50),

                        Forms\Components\TextInput::make('price_change_threshold')
                            ->label('Price Change Alert Threshold (%)')
                            ->helperText('Warn customer if price of an item in cart changes by this percent or more')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->default(20),

                        Forms\Components\TextInput::make('checkout_timeout_minutes')
                            ->label('Checkout Lock Timeout (Minutes)')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(120)
                            ->required()
                            ->helperText('Duration checkout sessions are locked to protect stock allocations')
                            ->default(30),

                        Forms\Components\Toggle::make('otp_required_guest')
                            ->label('Require OTP for Guest Checkouts')
                            ->helperText('Sends verification code to guest email during purchase steps')
                            ->default(true),

                        Forms\Components\Toggle::make('coupon_enabled')
                            ->label('Enable Checkout Coupons')
                            ->helperText('Allows entering promo/coupon codes on shopping cart page')
                            ->default(true),

                        Forms\Components\Toggle::make('merge_on_login')
                            ->label('Merge Guest Cart on Login')
                            ->helperText('Combines guest cart items with logged-in user cart items')
                            ->default(true),

                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label('Cart Add Rate Limit (per Minute)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(600)
                            ->required()
                            ->helperText('Maximum cart-add requests allowed per minute, per client')
                            ->default(60),

                        Forms\Components\TextInput::make('max_quantity')
                            ->label('Maximum Quantity per Line Item')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10000)
                            ->required()
                            ->helperText('Maximum quantity of a single product allowed in one cart line')
                            ->default(999),

                        Forms\Components\TextInput::make('guest_cookie_days')
                            ->label('Guest Cart Cookie Lifetime (Days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->required()
                            ->helperText('How long the guest cart identifier cookie persists')
                            ->default(7),
                    ])->columns(2),
            ]);
    }
}
