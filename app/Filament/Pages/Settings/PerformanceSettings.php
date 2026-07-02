<?php

namespace App\Filament\Pages\Settings;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Cache;

class PerformanceSettings extends SettingsPage
{
    protected static ?string $title = 'Performance & Cache';

    protected static string $settingsGroup = 'performance';

    protected static ?int $navigationSort = 18;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testCache')
                ->label('Test Cache')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Test Cache Driver')
                ->modalDescription('Writes and reads a test value to verify the configured cache driver is working.')
                ->modalSubmitActionLabel('Run Test')
                ->action(function () {
                    $driver = $this->data['cache_driver'] ?? config('cache.default');

                    try {
                        Cache::store($driver)->put('oe_test_cache_key', 'ok', 60);
                        $value = Cache::store($driver)->get('oe_test_cache_key');

                        if ($value === 'ok') {
                            Cache::store($driver)->forget('oe_test_cache_key');

                            Notification::make()
                                ->title('Cache working')
                                ->body("Driver '{$driver}' read/write verified.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Cache read failed')
                                ->body("Driver '{$driver}' wrote but returned unexpected value.")
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Cache error')
                            ->body("Driver '{$driver}': " . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Query Cache')
                    ->description('Enable database query result caching to reduce repeated SQL execution for identical queries.')
                    ->schema([
                        Forms\Components\Toggle::make('query_cache_enabled')
                            ->label('Enable Query Result Cache')
                            ->helperText('Caches identical database query results to reduce database load. Changes require a config cache clear.')
                            ->default(true),

                        Forms\Components\TextInput::make('query_cache_ttl')
                            ->label('Query Cache TTL (Minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->helperText('How long cached query results persist before re-fetching from the database.')
                            ->default(60),
                    ])->columns(2),

                Section::make('Cache Drivers & TTL Parameters')
                    ->description('Govern settings query caching, product hierarchy TTL parameters, and active store drivers.')
                    ->schema([
                        Forms\Components\Select::make('cache_driver')
                            ->label('Active Cache Driver')
                            ->options([
                                'redis' => 'Redis (Distributed In-Memory, Recommended)',
                            ])
                            ->required()
                            ->default('redis')
                            ->helperText('Production requires Redis. File and array drivers are not supported.'),

                        Forms\Components\Toggle::make('cache_settings')
                            ->label('Cache Global Settings')
                            ->helperText('Caches config settings to bypass SQL queries on site loads')
                            ->default(true),

                        Forms\Components\TextInput::make('cache_ttl_settings')
                            ->label('Settings Cache TTL (Minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required()
                            ->helperText('Recommended: 5 minutes. Settings changes clear cache automatically.')
                            ->default(5),

                        Forms\Components\Toggle::make('cache_sections')
                            ->label('Cache Page Sections')
                            ->helperText('Caches complex database page structures and header navigation links')
                            ->default(true),

                        Forms\Components\TextInput::make('cache_ttl_sections')
                            ->label('Page Sections Cache TTL (Minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->required()
                            ->default(60),

                        Forms\Components\Toggle::make('cache_manufacturers')
                            ->label('Cache Brand/Manufacturer Queries')
                            ->helperText('Caches automotive manufacturer list indexes and search options')
                            ->default(true),

                        Forms\Components\TextInput::make('cache_ttl_manufacturers')
                            ->label('Brand/Manufacturer Cache TTL (Minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->required()
                            ->default(60),
                    ])->columns(2),

                Section::make('Queue')
                    ->description('Configure queue worker retry behaviour for Redis-backed queues.')
                    ->schema([
                        Forms\Components\TextInput::make('queue_retry_after')
                            ->label('Queue Retry After (Seconds)')
                            ->numeric()
                            ->minValue(60)
                            ->maxValue(7200)
                            ->required()
                            ->helperText('Must exceed the longest job timeout. Default 3700 seconds (~62 minutes).')
                            ->default(3700),
                    ])->columns(2),
            ]);
    }
}
