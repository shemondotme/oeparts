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
                Section::make('Homepage Stats')
                    ->description('These numbers are displayed on the homepage hero section to build trust.')
                    ->schema([
                        Forms\Components\TextInput::make('customers_count')
                            ->label('Customers Count')
                            ->numeric()
                            ->minValue(0)
                            ->default(2500),
                        Forms\Components\TextInput::make('parts_count')
                            ->label('Parts Count')
                            ->numeric()
                            ->minValue(0)
                            ->default(1000000),
                        Forms\Components\TextInput::make('countries_count')
                            ->label('Countries Served')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(250)
                            ->default(27),
                        Forms\Components\TextInput::make('rating')
                            ->label('Rating')
                            ->helperText('Displayed as "X / 5.0"')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5)
                            ->step(0.1)
                            ->default(4.9),
                        Forms\Components\TextInput::make('orders_count')
                            ->label('Orders Count')
                            ->numeric()
                            ->minValue(0)
                            ->default(120000),
                        Forms\Components\Toggle::make('show_section')
                            ->label('Show Stats on Homepage')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
