<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class AuthSettings extends SettingsPage
{
    protected static ?string $title = 'Authentication Settings';

    protected static ?string $slug = 'auth-security-settings';

    protected static string $settingsGroup = 'auth';

    protected static ?int $navigationSort = 13;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('OTP Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('otp_length')
                            ->label('OTP Length')
                            ->numeric()
                            ->minValue(4)
                            ->maxValue(8)
                            ->required()
                            ->default(6),
                        Forms\Components\TextInput::make('otp_expiry_minutes')
                            ->label('OTP Expiry (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->default(10),
                        Forms\Components\TextInput::make('otp_max_attempts')
                            ->label('Max OTP Attempts')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->required()
                            ->default(3),
                        Forms\Components\TextInput::make('otp_resend_cooldown')
                            ->label('Resend Cooldown (seconds)')
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(600)
                            ->required()
                            ->default(60),
                    ])->columns(2),

                Section::make('Password Policy')
                    ->schema([
                        Forms\Components\TextInput::make('customer_password_min')
                            ->label('Customer Min Password Length')
                            ->numeric()
                            ->minValue(6)
                            ->maxValue(64)
                            ->required()
                            ->default(8),
                        Forms\Components\TextInput::make('admin_password_min')
                            ->label('Admin Min Password Length')
                            ->numeric()
                            ->minValue(8)
                            ->maxValue(64)
                            ->required()
                            ->default(12),
                    ])->columns(2),

                Section::make('Policy & Sessions')
                    ->schema([
                        Forms\Components\TextInput::make('customer_session_lifetime')
                            ->label('Customer Session Lifetime (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->required()
                            ->default(120),
                        Forms\Components\Toggle::make('guest_checkout_enabled')
                            ->label('Enable Guest Checkout')
                            ->default(true),
                        Forms\Components\Toggle::make('registration_enabled')
                            ->label('Enable Customer Registration')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
