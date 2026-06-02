<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Validation\Rule;

class SecuritySettings extends SettingsPage
{
    protected static ?string $title = 'Security Settings';

    protected static string $settingsGroup = 'security';

    protected static ?int $navigationSort = 22;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rate Limiting')
                    ->schema([
                        Forms\Components\TextInput::make('login_max_attempts')
                            ->label('Login Max Attempts')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->required()
                            ->default(5),
                        Forms\Components\TextInput::make('login_window_minutes')
                            ->label('Login Window (minutes)')
                            ->helperText('Time window for login attempt counting')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->default(15),
                        Forms\Components\TextInput::make('inquiry_max_per_email')
                            ->label('Max Inquiries per Email')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->default(10),
                    ])->columns(2),

                Section::make('IP Blocking')
                    ->schema([
                        Forms\Components\Toggle::make('ip_blocklist_enabled')
                            ->label('Enable IP Blocklist')
                            ->default(true),
                        Forms\Components\Textarea::make('blocked_ips')
                            ->label('Blocked IPs')
                            ->helperText('One IP per line. Supports individual IPs and CIDR notation.')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Security Features')
                    ->schema([
                        Forms\Components\Toggle::make('honeypot_enabled')
                            ->label('Enable Honeypot Spam Protection')
                            ->default(true),
                        Forms\Components\Toggle::make('csrf_enabled')
                            ->label('Enable CSRF Protection')
                            ->default(true),
                        Forms\Components\Toggle::make('force_https')
                            ->label('Force HTTPS')
                            ->default(false),
                        Forms\Components\Toggle::make('admin_2fa_required')
                            ->label('Require 2FA for Admins')
                            ->default(false),
                    ])->columns(2),
            ]);
    }
}
