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

                Section::make('IP Restrictions')
                    ->description('Control automated attacks by banning specific IPs or network ranges.')
                    ->schema([
                        Forms\Components\Toggle::make('ip_blocklist_enabled')
                            ->label('Enable IP Blocklist Restrictions')
                            ->helperText('When active, matching client requests are instantly returned a 403 Forbidden')
                            ->default(true),

                        Forms\Components\Textarea::make('blocked_ips')
                            ->label('Banned IP Whitelist/Blocklist')
                            ->helperText('Enter one IP per line. Supports raw IP format and CIDR subnets (e.g. 192.168.1.0/24)')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Global Security Flags')
                    ->description('Toggle web security, HTTPS force flags, and MFA enforcement.')
                    ->schema([
                        Forms\Components\Toggle::make('honeypot_enabled')
                            ->label('Enable Honeypot Spam Protection')
                            ->helperText('Adds hidden inputs to trap automated bots filling forms')
                            ->default(true),

                        Forms\Components\Toggle::make('csrf_enabled')
                            ->label('Enable CSRF Token Protection')
                            ->helperText('Enforce cross-site forgery token matches on all POST forms')
                            ->default(true),

                        Forms\Components\Toggle::make('force_https')
                            ->label('Force HTTPS SSL Encryption')
                            ->helperText('Redirects all HTTP links to encrypted HTTPS automatically')
                            ->default(false),

                    ])->columns(2),
            ]);
    }
}
