<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class IntegrationsSettings extends SettingsPage
{
    protected static ?string $title = 'Integrations';

    protected static string $settingsGroup = 'integrations';

    protected static ?int $navigationSort = 35;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-puzzle-piece';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Google')
                    ->schema([
                        Forms\Components\TextInput::make('gtm_id')
                            ->label('Google Tag Manager ID')
                            ->placeholder('GTM-XXXXXXX')
                            ->helperText('e.g. GTM-ABC1234')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\TextInput::make('gsc_verification')
                            ->label('Google Search Console Verification')
                            ->helperText('The meta verification string from Google Search Console')
                            ->maxLength(255)
                            ->default(null),
                    ])->columns(2),

                Section::make('Analytics')
                    ->schema([
                        Forms\Components\TextInput::make('ga4_measurement_id')
                            ->label('GA4 Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->maxLength(50)
                            ->default(null),
                    ]),

                Section::make('Social & Chat')
                    ->schema([
                        Forms\Components\TextInput::make('fb_pixel_id')
                            ->label('Facebook Pixel ID')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\TextInput::make('crisp_website_id')
                            ->label('Crisp Chat Website ID')
                            ->maxLength(50)
                            ->default(null),
                    ])->columns(2),
            ]);
    }
}
