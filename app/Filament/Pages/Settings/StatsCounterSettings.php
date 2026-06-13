<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class StatsCounterSettings extends SettingsPage
{
    protected static ?string $title = 'Stats Counter';

    protected static string $settingsGroup = 'stats_counter';

    protected static ?int $navigationSort = 36;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chart-bar-square';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Homepage Statistics Display')
                    ->description('These numbers are displayed on the public landing page hero grid block to establish trust.')
                    ->schema([
                        Forms\Components\TextInput::make('customers_count')
                            ->label('Total Active Customers')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('2500')
                            ->default(2500),

                        Forms\Components\TextInput::make('parts_count')
                            ->label('Catalog Part Numbers Count')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('1000000')
                            ->default(1000000),

                        Forms\Components\TextInput::make('countries_count')
                            ->label('Countries Served Count')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(250)
                            ->placeholder('27')
                            ->default(27),

                        Forms\Components\TextInput::make('rating')
                            ->label('Store Trust Rating')
                            ->helperText('Shown as a fractional score (e.g. 4.9 / 5.0 rating)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5)
                            ->step(0.1)
                            ->placeholder('4.9')
                            ->default(4.9),

                        Forms\Components\TextInput::make('orders_count')
                            ->label('Processed Orders Count')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('120000')
                            ->default(120000),

                        Forms\Components\Toggle::make('show_section')
                            ->label('Render Stats Section on Homepage')
                            ->helperText('Toggles layout visibility of stats numbers in the public hero section')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
