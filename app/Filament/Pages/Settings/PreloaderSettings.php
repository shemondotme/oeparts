<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

class PreloaderSettings extends SettingsPage
{
    protected static ?string $title = 'Preloader Settings';

    protected static string $settingsGroup = 'preloader';

    protected static ?int $navigationSort = 25;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Preloader Configuration')
                    ->description('Control the full-screen loading animation on storefront pages.')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Preloader')
                            ->helperText('Show a loading screen on page navigation')
                            ->default(false),

                        Forms\Components\Select::make('path_mode')
                            ->label('Path Mode')
                            ->options([
                                'all' => 'All Pages',
                                'include' => 'Include Only',
                                'exclude' => 'Exclude Only',
                            ])
                            ->default('all'),

                        Forms\Components\TagsInput::make('path_patterns')
                            ->label('Path Patterns')
                            ->helperText('URL patterns to match (e.g., /parts/*, /cart)')
                            ->default([]),

                        Forms\Components\TextInput::make('min_display_ms')
                            ->label('Minimum Display Time (ms)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5000)
                            ->default(450),

                        Forms\Components\TextInput::make('max_display_ms')
                            ->label('Maximum Display Time (ms)')
                            ->numeric()
                            ->minValue(500)
                            ->maxValue(60000)
                            ->default(6000),
                    ])->columns(2),

                Section::make('Preloader Text')
                    ->description('Customize the multilang text displayed during the preloader animation.')
                    ->schema([
                        Tabs::make('Preloader Text Locales')
                            ->columnSpanFull()
                            ->tabs([
                                Tab::make('English')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('headline.en')
                                            ->label('Headline (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('spec_line.en')
                                            ->label('Spec Line (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('subline.en')
                                            ->label('Subline (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('status_line.en')
                                            ->label('Status Line (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('foot_left.en')
                                            ->label('Footer Left (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('foot_right.en')
                                            ->label('Footer Right (EN)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('aria_label.en')
                                            ->label('ARIA Label (EN)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Deutsch')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('headline.de')
                                            ->label('Headline (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('spec_line.de')
                                            ->label('Spec Line (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('subline.de')
                                            ->label('Subline (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('status_line.de')
                                            ->label('Status Line (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('foot_left.de')
                                            ->label('Footer Left (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('foot_right.de')
                                            ->label('Footer Right (DE)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('aria_label.de')
                                            ->label('ARIA Label (DE)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Lietuvių')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('headline.lt')
                                            ->label('Headline (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('spec_line.lt')
                                            ->label('Spec Line (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('subline.lt')
                                            ->label('Subline (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('status_line.lt')
                                            ->label('Status Line (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('foot_left.lt')
                                            ->label('Footer Left (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('foot_right.lt')
                                            ->label('Footer Right (LT)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('aria_label.lt')
                                            ->label('ARIA Label (LT)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Français')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('headline.fr')
                                            ->label('Headline (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('spec_line.fr')
                                            ->label('Spec Line (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('subline.fr')
                                            ->label('Subline (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('status_line.fr')
                                            ->label('Status Line (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('foot_left.fr')
                                            ->label('Footer Left (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('foot_right.fr')
                                            ->label('Footer Right (FR)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('aria_label.fr')
                                            ->label('ARIA Label (FR)')
                                            ->maxLength(255),
                                    ]),
                                Tab::make('Español')
                                    ->icon('heroicon-m-language')
                                    ->schema([
                                        Forms\Components\TextInput::make('headline.es')
                                            ->label('Headline (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('spec_line.es')
                                            ->label('Spec Line (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('subline.es')
                                            ->label('Subline (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('status_line.es')
                                            ->label('Status Line (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('foot_left.es')
                                            ->label('Footer Left (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('foot_right.es')
                                            ->label('Footer Right (ES)')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('aria_label.es')
                                            ->label('ARIA Label (ES)')
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
