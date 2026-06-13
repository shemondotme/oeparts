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
                Section::make('Google Tag Manager & Console')
                    ->description('Link Tag Manager containers and Search Console tracking metrics.')
                    ->schema([
                        Forms\Components\TextInput::make('gtm_id')
                            ->label('Google Tag Manager Container ID')
                            ->placeholder('GTM-XXXXXXX')
                            ->helperText('Container ID format: GTM-XXXXXXX')
                            ->maxLength(50)
                            ->default(null),

                        Forms\Components\TextInput::make('gsc_verification')
                            ->label('Google Search Console Meta Token')
                            ->placeholder('google-site-verification-token')
                            ->helperText('The content attribute value from Google verification meta tags')
                            ->maxLength(255)
                            ->default(null),
                    ])->columns(2),

                Section::make('Google Analytics 4 (GA4)')
                    ->description('Expose storefront ecommerce tracking data using standard GA4 streams.')
                    ->schema([
                        Forms\Components\TextInput::make('ga4_measurement_id')
                            ->label('GA4 Stream Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText('E.g. G-H2KL987YZ6')
                            ->maxLength(50)
                            ->default(null),
                    ]),

                Section::make('Marketing Pixels & Customer Service')
                    ->description('Set Facebook tracking ids and load support chat widgets.')
                    ->schema([
                        Forms\Components\TextInput::make('fb_pixel_id')
                            ->label('Facebook Pixel ID')
                            ->placeholder('123456789012345')
                            ->maxLength(50)
                            ->default(null),

                        Forms\Components\TextInput::make('crisp_website_id')
                            ->label('Crisp Website ID')
                            ->placeholder('e.g. 5d57b543-9876-4321-a000-a00000000000')
                            ->maxLength(50)
                            ->default(null),
                    ])->columns(2),
            ]);
    }
}
