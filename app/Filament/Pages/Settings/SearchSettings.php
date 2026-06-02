<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class SearchSettings extends SettingsPage
{
    protected static ?string $title = 'Search Settings';

    protected static string $settingsGroup = 'search';

    protected static ?int $navigationSort = 15;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Search Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('min_chars')
                            ->label('Minimum Characters')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required()
                            ->default(3),
                        Forms\Components\TextInput::make('autocomplete_count')
                            ->label('Autocomplete Results')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->required()
                            ->default(5),
                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label('Rate Limit (per minute)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->required()
                            ->default(30),
                        Forms\Components\TextInput::make('max_results')
                            ->label('Maximum Results')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500)
                            ->required()
                            ->default(50),
                    ])->columns(2),

                Section::make('Logging')
                    ->schema([
                        Forms\Components\Toggle::make('log_searches')
                            ->label('Log All Searches')
                            ->default(true),
                        Forms\Components\Toggle::make('log_failed')
                            ->label('Log Failed Searches')
                            ->default(true),
                        Forms\Components\TextInput::make('log_retention_days')
                            ->label('Log Retention (days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required()
                            ->default(90),
                    ])->columns(2),

                Section::make('Features')
                    ->schema([
                        Forms\Components\Toggle::make('cross_ref_enabled')
                            ->label('Enable Cross-Reference Search')
                            ->default(true),
                        Forms\Components\Toggle::make('partial_match_enabled')
                            ->label('Enable Partial Match')
                            ->default(true),
                        Forms\Components\TextInput::make('partial_match_min_length')
                            ->label('Partial Match Min Length')
                            ->numeric()
                            ->minValue(3)
                            ->maxValue(20)
                            ->default(4),
                    ])->columns(2),
            ]);
    }
}
