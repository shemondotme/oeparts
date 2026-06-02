<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class EmailSettings extends SettingsPage
{
    protected static ?string $title = 'Email Settings';

    protected static string $settingsGroup = 'email';

    protected static ?int $navigationSort = 14;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('From Address')
                    ->schema([
                        Forms\Components\TextInput::make('from_name')
                            ->label('From Name')
                            ->maxLength(255)
                            ->default('OeParts'),
                        Forms\Components\TextInput::make('from_address')
                            ->label('From Email')
                            ->email()
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('reply_to')
                            ->label('Reply To')
                            ->email()
                            ->maxLength(255)
                            ->default(null),
                    ])->columns(2),

                Section::make('SMTP Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('smtp_host')
                            ->label('SMTP Host')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('smtp_port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(587),
                        Forms\Components\Select::make('smtp_encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                '' => 'None',
                            ])
                            ->default('tls'),
                        Forms\Components\TextInput::make('smtp_username')
                            ->label('SMTP Username')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('smtp_password')
                            ->label('SMTP Password')
                            ->password()
                            ->revealable()
                            ->helperText('Stored encrypted at rest')
                            ->default(null),
                    ])->columns(2),

                Section::make('Admin Notifications')
                    ->schema([
                        Forms\Components\Toggle::make('admin_notify_new_order')
                            ->label('Notify Admin on New Order')
                            ->default(true),
                        Forms\Components\Toggle::make('admin_notify_new_inquiry')
                            ->label('Notify Admin on New Inquiry')
                            ->default(true),
                        Forms\Components\TextInput::make('admin_notify_email')
                            ->label('Notification Email Address')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Leave empty to use general site email')
                            ->default(null),
                    ])->columns(2),
            ]);
    }
}
