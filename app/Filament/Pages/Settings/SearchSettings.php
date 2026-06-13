<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class SearchSettings extends SettingsPage
{
    protected static ?string $title = 'Search Settings';

    protected static string $settingsGroup = 'search';

    protected static ?int $navigationSort = 16;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Search Engine Behavior')
                    ->description('Tune criteria controls for normalized query operations and storefront thresholds.')
                    ->schema([
                        Forms\Components\TextInput::make('min_chars')
                            ->label('Minimum Query Characters')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required()
                            ->helperText('Characters required before search results list starts loading')
                            ->default(3),

                        Forms\Components\TextInput::make('autocomplete_count')
                            ->label('Max Autocomplete Dropdown Options')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->required()
                            ->helperText('Number of instant matched choices shown in search bar dropdown')
                            ->default(5),

                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label('Rate Limit (Queries per Minute)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->required()
                            ->helperText('Maximum search requests per client session to block bots')
                            ->default(30),

                        Forms\Components\TextInput::make('max_results')
                            ->label('Max Results per Search Page')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500)
                            ->required()
                            ->helperText('Hard cutoff for total matched items returned in standard listing page grids')
                            ->default(50),
                    ])->columns(2),

                Section::make('Search & Diagnostic Logging')
                    ->description('Log operations to optimize query indexes and identify missing OEM part demands.')
                    ->schema([
                        Forms\Components\Toggle::make('log_searches')
                            ->label('Log All Keyword Search Queries')
                            ->helperText('Saves user inputs to track popular searches and catalog items')
                            ->default(true),

                        Forms\Components\Toggle::make('log_failed')
                            ->label('Log Failed Searches')
                            ->helperText('Saves queries yielding zero matches (extremely useful to expand supplier catalogs)')
                            ->default(true),

                        Forms\Components\TextInput::make('log_retention_days')
                            ->label('Audit Log Retention (Days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required()
                            ->helperText('Days to keep search audit histories before automatic cleanups')
                            ->default(90),
                    ])->columns(2),

                Section::make('Search Matching Options')
                    ->description('Enable partial string matching and cross-reference code alignments.')
                    ->schema([
                        Forms\Components\Toggle::make('cross_ref_enabled')
                            ->label('Enable Cross-Reference Identifiers')
                            ->helperText('Matches equivalent replacement codes when exact OEM matches are missing')
                            ->default(true),

                        Forms\Components\Toggle::make('partial_match_enabled')
                            ->label('Enable Partial Code Matching')
                            ->helperText('Allows matching partial code segments if full numbers are not matched')
                            ->default(true),

                        Forms\Components\TextInput::make('partial_match_min_length')
                            ->label('Partial Match Code Min Length')
                            ->numeric()
                            ->minValue(3)
                            ->maxValue(20)
                            ->helperText('Minimum character length of user input before running partial queries')
                            ->default(4),
                    ])->columns(2),
            ]);
    }
}
