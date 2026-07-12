<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class SEOSettings extends SettingsPage
{
    protected static ?string $title = 'SEO & Meta';

    protected static ?string $slug = 'seo-settings';

    protected static string $settingsGroup = 'seo';

    protected static ?int $navigationSort = 20;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Global Meta Title Templates')
                    ->description('Placeholders like {oem}, {min}, {max}, {manufacturer}, or {brand} are evaluated dynamically.')
                    ->schema([
                        Forms\Components\TextInput::make('home_title')
                            ->label('Homepage Meta Title')
                            ->maxLength(60)
                            ->helperText('Ideal: 50-60 characters')
                            ->default('Buy Genuine OEM Auto Parts Online | OeParts'),

                        Forms\Components\Textarea::make('home_description')
                            ->label('Homepage Meta Description')
                            ->rows(2)
                            ->maxLength(160)
                            ->helperText('Ideal: 150-160 characters')
                            ->default(null),

                        Forms\Components\TextInput::make('brand_title_template')
                            ->label('Brand/Manufacturer Page Title Template')
                            ->maxLength(100)
                            ->helperText('Available placeholders: {brand}')
                            ->default('Genuine {brand} OEM Parts — Buy Online'),
                    ])->columns(2),

                Section::make('SEO Directives & Crawling Defaults')
                    ->description('Configure search crawler policies and sitemap indexes.')
                    ->schema([
                        Forms\Components\Select::make('default_robots')
                            ->label('Default Robots Directive')
                            ->options([
                                'index,follow' => 'Index, Follow (Recommended)',
                                'noindex,follow' => 'No Index, Follow',
                                'index,nofollow' => 'Index, No Follow',
                                'noindex,nofollow' => 'No Index, No Follow',
                            ])
                            ->default('index,follow'),

                        Forms\Components\Toggle::make('google_ping_enabled')
                            ->label('Ping Google on Sitemap Updates')
                            ->helperText('Automatically requests recrawling when products updates trigger new sitemap builds')
                            ->default(true),

                        Forms\Components\TextInput::make('sitemap_search_log_days')
                            ->label('Sitemap Query History (Days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->helperText('Include custom keyword routes based on logs from the last X days')
                            ->default(90),

                        Forms\Components\TextInput::make('twitter_handle')
                            ->label('Company Twitter Handle')
                            ->placeholder('@oeparts')
                            ->maxLength(50)
                            ->default(null),
                    ])->columns(2),

                Section::make('Open Graph Defaults')
                    ->description('Default meta shares rendered across social feeds (Facebook, LinkedIn, Discord). Site name is taken automatically from General Settings.')
                    ->schema([
                        Forms\Components\FileUpload::make('default_og_image')
                            ->label('Default OG Fallback Image')
                            ->image()
                            ->disk('public')
                            ->directory('og-images')
                            ->maxSize(1024)
                            ->helperText('Image shown when sharing links lacking specific graphics (max size 1MB)')
                            ->visibility('public'),
                    ])->columns(2),

                Section::make('Search Results Templates')
                    ->description('Customise the title and meta description shown on internal search result pages — including individual OEM part pages (/parts/{oem}), which are rendered by the search results page.')
                    ->schema([
                        Forms\Components\TextInput::make('search_results_title_template')
                            ->label('Search Results Title Template')
                            ->maxLength(100)
                            ->helperText('Placeholders: {oem}, {count}, {site}, {min}, {max}. e.g. "Buy OEM Part {oem} — From €{min} | {site}"')
                            ->default('Buy OEM Part {oem} — From €{min} | OeParts'),

                        Forms\Components\Textarea::make('search_results_meta_template')
                            ->label('Search Results Meta Description Template')
                            ->rows(2)
                            ->maxLength(200)
                            ->helperText('Placeholders: {oem}, {count}, {site}, {min}, {max}. e.g. "Genuine OEM part {oem}, from €{min}, on {site}."')
                            ->default('Genuine OEM part {oem}. Verified EU suppliers. Prices from €{min}. Insured delivery in 1–5 days to all 27 EU countries. VAT invoice included.'),
                    ])->columns(2),

                Section::make('Webmaster Verification Codes')
                    ->description('Verify website ownership with search console integrations.')
                    ->schema([
                        Forms\Components\TextInput::make('google_verification')
                            ->label('Google Site Verification Key')
                            ->maxLength(255)
                            ->placeholder('google-verification-token')
                            ->default(null),

                        Forms\Components\TextInput::make('bing_verification')
                            ->label('Bing Site Verification Key')
                            ->maxLength(255)
                            ->placeholder('bing-verification-token')
                            ->default(null),
                    ])->columns(2),
            ]);
    }
}
