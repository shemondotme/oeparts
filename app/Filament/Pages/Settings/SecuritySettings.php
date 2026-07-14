<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class SecuritySettings extends SettingsPage
{
    protected static ?string $title = 'Security Settings';

    protected static string $settingsGroup = 'security';

    protected static ?int $navigationSort = 22;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('OTP / Two-Step Verification')
                    ->description('Master switch for every storefront OTP step. This is the single control for pausing verification during testing/staging.')
                    ->schema([
                        Forms\Components\Toggle::make('otp_enabled')
                            ->label('Enable OTP / Two-Step Verification (Storefront)')
                            ->helperText('When OFF, every storefront OTP step is skipped entirely — new accounts and guest checkouts are auto-verified with no code sent. Use this to unblock testing when no test SMTP is available. Always re-enable before going live.')
                            ->default(true),
                    ]),

                Section::make('Rate Limiting & Protection')
                    ->description('Set throttle parameters to prevent password brute-forcing and form submission spams.')
                    ->schema([
                        Forms\Components\TextInput::make('login_max_attempts')
                            ->label('Max Login Attempts')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->required()
                            ->helperText('Allowed failed signin attempts before IP is throttled/locked')
                            ->default(5),

                        Forms\Components\TextInput::make('login_window_minutes')
                            ->label('Login Attempt Time Window (Minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->helperText('Failed attempt tracking session block lifetime')
                            ->default(15),

                        Forms\Components\TextInput::make('inquiry_max_per_email')
                            ->label('Max Daily Custom Quotes per Email')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->helperText('Max custom part requests allowed per email block daily')
                            ->default(10),
                    ])->columns(2),

                Section::make('IP Restrictions & Core Protections')
                    ->description('Automated-attack IP/CIDR banning is managed as data, not a setting. Honeypot spam protection, CSRF token verification, and production HTTPS enforcement are always active — core application behavior, not optional.')
                    ->schema([
                        Forms\Components\Placeholder::make('ip_blocklist_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new \Illuminate\Support\HtmlString(
                                'Banned IPs and CIDR ranges are managed on the <a href="'
                                . \App\Filament\Resources\IpBlocklistResource::getUrl()
                                . '" class="fi-link text-primary-600">IP Blocklist</a> page — each entry is checked on every request with no restart or cache-clear needed.'
                            )),
                    ]),

                Section::make('Session')
                    ->description('Control admin panel session expiry for security compliance.')
                    ->schema([
                        Forms\Components\TextInput::make('session_lifetime')
                            ->label('Admin Session Lifetime (Minutes)')
                            ->numeric()
                            ->minValue(15)
                            ->maxValue(1440)
                            ->required()
                            ->helperText('Minutes of inactivity before the admin panel session expires')
                            ->default(120),
                    ])->columns(2),
            ]);
    }
}
