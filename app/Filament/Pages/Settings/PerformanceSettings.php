<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class PerformanceSettings extends SettingsPage
{
    protected static ?string $title = 'Performance & Cache';

    protected static string $settingsGroup = 'performance';

    protected static ?int $navigationSort = 18;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cache Configuration')
                    ->schema([
                        Forms\Components\Select::make('cache_driver')
                            ->label('Cache Driver')
                            ->options([
                                'redis' => 'Redis',
                                'file' => 'File',
                                'array' => 'Array (Development)',
                            ])
                            ->default('redis'),
                        Forms\Components\Toggle::make('cache_sections')
                            ->label('Cache Sections')
                            ->default(true),
                        Forms\Components\TextInput::make('cache_ttl_sections')
                            ->label('Sections Cache TTL (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->required()
                            ->default(60),
                        Forms\Components\Toggle::make('cache_settings')
                            ->label('Cache Settings')
                            ->default(true),
                        Forms\Components\TextInput::make('cache_ttl_settings')
                            ->label('Settings Cache TTL (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->default(5),
                        Forms\Components\Toggle::make('cache_manufacturers')
                            ->label('Cache Manufacturers')
                            ->default(true),
                        Forms\Components\TextInput::make('cache_ttl_manufacturers')
                            ->label('Manufacturers Cache TTL (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->required()
                            ->default(60),
                    ])->columns(2),
            ]);
    }
}
