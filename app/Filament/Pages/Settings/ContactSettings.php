<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class ContactSettings extends SettingsPage
{
    protected static ?string $title = 'Contact Information';

    protected static string $settingsGroup = 'contact';

    protected static ?int $navigationSort = 34;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-phone';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact Details')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(30)
                            ->default(null),
                        Forms\Components\TextInput::make('email')
                            ->label('Contact Email')
                            ->email()
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\Textarea::make('address')
                            ->label('Business Address')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull()
                            ->default(null),
                    ])->columns(2),

                Section::make('Business Hours')
                    ->schema([
                        Forms\Components\Textarea::make('hours')
                            ->label('Business Hours (Multilang JSON)')
                            ->helperText('JSON object with keys: en, de, lt, fr, es')
                            ->rows(3)
                            ->columnSpanFull()
                            ->default(null),
                    ]),

                Section::make('Messaging')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp Number')
                            ->tel()
                            ->maxLength(30)
                            ->default(null),
                        Forms\Components\TextInput::make('viber')
                            ->label('Viber Number')
                            ->tel()
                            ->maxLength(30)
                            ->default(null),
                    ])->columns(2),

                Section::make('Social Media')
                    ->schema([
                        Forms\Components\TextInput::make('facebook_url')
                            ->label('Facebook URL')
                            ->url()
                            ->maxLength(500)
                            ->default(null),
                        Forms\Components\TextInput::make('linkedin_url')
                            ->label('LinkedIn URL')
                            ->url()
                            ->maxLength(500)
                            ->default(null),
                    ])->columns(2),
            ]);
    }
}
