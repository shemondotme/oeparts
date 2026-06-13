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
                Section::make('One-Time Password (OTP) Auth')
                    ->description('Manage security variables for passwordless guest and B2B user signins.')
                    ->schema([
                        Forms\Components\TextInput::make('otp_length')
                            ->label('OTP Code Length')
                            ->numeric()
                            ->minValue(4)
                            ->maxValue(8)
                            ->required()
                            ->helperText('Number of digits generated for login verification codes')
                            ->default(6),

                        Forms\Components\TextInput::make('otp_expiry_minutes')
                            ->label('OTP Code Lifetime (Minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->helperText('Minutes before a generated OTP code expires')
                            ->default(10),

                        Forms\Components\TextInput::make('otp_max_attempts')
                            ->label('Maximum Login Attempts')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->required()
                            ->helperText('Allowed OTP entries before locking the email session')
                            ->default(3),

                        Forms\Components\TextInput::make('otp_resend_cooldown')
                            ->label('Resend Cooldown (Seconds)')
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(600)
                            ->required()
                            ->helperText('Cooldown delay required before requesting another OTP')
                            ->default(60),
                    ])->columns(2),

                Section::make('Minimum Password Security')
                    ->description('Set password complexity length constraints for account registration.')
                    ->schema([
                        Forms\Components\TextInput::make('customer_password_min')
                            ->label('Customer Password Min Length')
                            ->numeric()
                            ->minValue(6)
                            ->maxValue(64)
                            ->required()
                            ->helperText('Minimum character length for customer portal passwords')
                            ->default(8),

                        Forms\Components\TextInput::make('admin_password_min')
                            ->label('Admin Panel Password Min Length')
                            ->numeric()
                            ->minValue(8)
                            ->maxValue(64)
                            ->required()
                            ->helperText('Minimum character length for administrative user accounts')
                            ->default(12),
                    ])->columns(2),

                Section::make('Registration & Account Policy')
                    ->description('Enable/disable registration endpoints and control session durations.')
                    ->schema([
                        Forms\Components\TextInput::make('customer_session_lifetime')
                            ->label('Portal Session Lifetime (Minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->required()
                            ->helperText('Minutes of inactivity before logging out a customer session')
                            ->default(120),

                        Forms\Components\Toggle::make('guest_checkout_enabled')
                            ->label('Allow Guest Checkout')
                            ->helperText('Allow users to place orders without creating a formal portal account')
                            ->default(true),

                        Forms\Components\Toggle::make('registration_enabled')
                            ->label('Enable Customer Registration')
                            ->helperText('Expose customer signup and registration page forms on storefront')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
