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
                Section::make('Google Tag Manager')
                    ->description('Injects the standard GTM container snippet into every storefront page (head script + body noscript iframe).')
                    ->schema([
                        Forms\Components\TextInput::make('gtm_id')
                            ->label('Google Tag Manager Container ID')
                            ->placeholder('GTM-XXXXXXX')
                            ->helperText('Container ID format: GTM-XXXXXXX')
                            ->maxLength(50)
                            ->default(null),

                        Forms\Components\Placeholder::make('gsc_verification_note')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                'Search Console verification is set on the <a href="'
                                . SEOSettings::getUrl()
                                . '" class="fi-link text-primary-600">SEO &amp; Meta</a> page, alongside the other webmaster verification codes.'
                            )),
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
