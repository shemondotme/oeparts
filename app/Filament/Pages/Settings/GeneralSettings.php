<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class GeneralSettings extends SettingsPage
{
    protected static ?string $title = 'General Settings';

    protected static string $settingsGroup = 'general';

    protected static ?int $navigationSort = 10;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site Information')
                    ->schema([
                        Forms\Components\TextInput::make('site_name')
                            ->label('Site Name')
                            ->maxLength(255)
                            ->required()
                            ->default('OeParts'),
                        Forms\Components\TextInput::make('site_url')
                            ->label('Site URL')
                            ->url()
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('site_email')
                            ->label('Site Email')
                            ->email()
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('site_phone')
                            ->label('Site Phone')
                            ->tel()
                            ->minLength(5)
                            ->maxLength(30)
                            ->default(null),
                        Forms\Components\Textarea::make('site_address')
                            ->label('Site Address')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull()
                            ->default(null),
                    ])->columns(2),

                Section::make('Localization & Branding')
                    ->schema([
                        Forms\Components\TextInput::make('tagline')
                            ->label('Tagline')
                            ->maxLength(255)
                            ->default('Genuine OEM Auto Parts'),
                        Forms\Components\Select::make('default_locale')
                            ->label('Default Locale')
                            ->options([
                                'en' => 'English',
                                'de' => 'Deutsch',
                                'lt' => 'Lietuvių',
                                'fr' => 'Français',
                                'es' => 'Español',
                            ])
                            ->required()
                            ->default('en'),
                        Forms\Components\Select::make('timezone')
                            ->label('Timezone')
                            ->options(collect(\DateTimeZone::listIdentifiers(\DateTimeZone::EUROPE))
                                ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                ->toArray())
                            ->searchable()
                            ->required()
                            ->default('Europe/Vilnius'),
                        Forms\Components\Select::make('date_format')
                            ->label('Date Format')
                            ->options([
                                'd/m/Y' => 'DD/MM/YYYY (e.g. 14/03/2025)',
                                'Y-m-d' => 'YYYY-MM-DD (e.g. 2025-03-14)',
                                'm/d/Y' => 'MM/DD/YYYY (e.g. 03/14/2025)',
                                'j F Y' => 'D Month YYYY (e.g. 14 March 2025)',
                            ])
                            ->required()
                            ->default('d/m/Y'),
                        Forms\Components\Select::make('currency')
                            ->label('Default Currency')
                            ->options([
                                'EUR' => 'EUR (€)',
                                'USD' => 'USD ($)',
                                'GBP' => 'GBP (£)',
                                'CHF' => 'CHF (Fr)',
                                'PLN' => 'PLN (zł)',
                                'SEK' => 'SEK (kr)',
                            ])
                            ->required()
                            ->default('EUR'),
                        Forms\Components\TextInput::make('currency_symbol')
                            ->label('Currency Symbol')
                            ->maxLength(10)
                            ->required()
                            ->default('€'),
                    ])->columns(2),

                Section::make('Scripts')
                    ->schema([
                        Forms\Components\Textarea::make('header_scripts')
                            ->label('Header Scripts')
                            ->helperText('Scripts injected before </head>')
                            ->rows(4)
                            ->columnSpanFull()
                            ->default(null),
                        Forms\Components\Textarea::make('footer_scripts')
                            ->label('Footer Scripts')
                            ->helperText('Scripts injected before </body>')
                            ->rows(4)
                            ->columnSpanFull()
                            ->default(null),
                    ]),
            ]);
    }
}
