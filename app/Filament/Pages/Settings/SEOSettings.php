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
                Section::make('Meta Templates')
                    ->description('Use {oem}, {min}, {max}, {manufacturer}, {brand} as placeholders')
                    ->schema([
                        Forms\Components\TextInput::make('home_title')
                            ->label('Homepage Title')
                            ->maxLength(60)
                            ->helperText('Max 60 characters')
                            ->default('Buy Genuine OEM Auto Parts Online | OeParts'),
                        Forms\Components\Textarea::make('home_description')
                            ->label('Homepage Meta Description')
                            ->rows(2)
                            ->maxLength(160)
                            ->helperText('Max 160 characters')
                            ->default(null),
                        Forms\Components\TextInput::make('oem_title_template')
                            ->label('OEM Title Template')
                            ->maxLength(100)
                            ->helperText('Placeholders: {oem}, {min}, {manufacturer}')
                            ->default('Buy {oem} — From €{min} | OeParts'),
                        Forms\Components\Textarea::make('oem_description_template')
                            ->label('OEM Description Template')
                            ->rows(2)
                            ->maxLength(200)
                            ->helperText('Placeholders: {oem}, {manufacturer}')
                            ->default('Genuine {oem} parts from {manufacturer}.'),
                        Forms\Components\TextInput::make('brand_title_template')
                            ->label('Brand Title Template')
                            ->maxLength(100)
                            ->helperText('Placeholders: {brand}')
                            ->default('Genuine {brand} OEM Parts — Buy Online'),
                    ])->columns(2),

                Section::make('Defaults')
                    ->schema([
                        Forms\Components\Select::make('default_robots')
                            ->label('Default Robots Meta')
                            ->options([
                                'index,follow' => 'Index, Follow',
                                'noindex,follow' => 'No Index, Follow',
                                'index,nofollow' => 'Index, No Follow',
                                'noindex,nofollow' => 'No Index, No Follow',
                            ])
                            ->default('index,follow'),
                        Forms\Components\Toggle::make('maintenance_noindex')
                            ->label('Noindex During Maintenance')
                            ->default(true),
                        Forms\Components\Toggle::make('google_ping_enabled')
                            ->label('Ping Google on Sitemap Update')
                            ->default(true),
                        Forms\Components\TextInput::make('sitemap_search_log_days')
                            ->label('Sitemap Search Log Days')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->default(90),
                        Forms\Components\TextInput::make('twitter_handle')
                            ->label('Twitter Handle')
                            ->maxLength(50)
                            ->default(null),
                    ])->columns(2),

                Section::make('Open Graph')
                    ->schema([
                        Forms\Components\TextInput::make('og_site_name')
                            ->label('OG Site Name')
                            ->maxLength(255)
                            ->default('OeParts'),
                        Forms\Components\FileUpload::make('default_og_image')
                            ->label('Default OG Image')
                            ->image()
                            ->directory('og-images')
                            ->maxSize(1024)
                            ->visibility('public'),
                    ])->columns(2),

                Section::make('Verification')
                    ->schema([
                        Forms\Components\TextInput::make('google_verification')
                            ->label('Google Site Verification')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('bing_verification')
                            ->label('Bing Site Verification')
                            ->maxLength(255)
                            ->default(null),
                    ])->columns(2),
            ]);
    }
}
