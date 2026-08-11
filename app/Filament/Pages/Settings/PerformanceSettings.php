<?php

namespace App\Filament\Pages\Settings;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Cache;

class PerformanceSettings extends SettingsPage
{
    protected static ?string $title = 'Performance & Cache';

    protected static string $settingsGroup = 'performance';

    protected static ?int $navigationSort = 18;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Action::make('testCache')
                ->label('Test Cache')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Test Cache Driver')
                ->modalDescription('Writes and reads a test value to verify the configured cache driver is working.')
                ->modalSubmitActionLabel('Run Test')
                ->action(function () {
                    $driver = config('cache.default');

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
                    ->description('Database query result caching.')
                    ->schema([
                        Forms\Components\Placeholder::make('query_cache_note')
                            ->label('')
                            ->content('No query-result caching layer exists in this application — this section previously showed live toggle/TTL inputs that were never read anywhere. The caching that does exist (page sections, manufacturer lists, below) is per-feature, not a generic query cache.'),
                    ]),

                Section::make('Cache Drivers & TTL Parameters')
                    ->description('Govern product hierarchy TTL parameters and active store drivers.')
                    ->schema([
                        Forms\Components\Placeholder::make('cache_driver_note')
                            ->label('')
                            ->content('The active cache driver is set via the CACHE_STORE environment variable (Redis required in production — CLAUDE.md rule #41), not this panel.'),

                        Forms\Components\Placeholder::make('cache_settings_note')
                            ->label('')
                            ->content('Settings themselves are always cached with a hardcoded 5-minute TTL, set directly in SettingsService — by design, to avoid a circular dependency (reading the settings() cache TTL from settings() itself). This cannot be made settings-driven.'),

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

                Section::make('Image Optimization')
                    ->description('Applies to Media Library / editor / logo uploads — never to refund evidence photos, which must stay exactly as the customer submitted them.')
                    ->schema([
                        Forms\Components\Toggle::make('optimize_images')
                            ->label('Optimize Uploaded Images')
                            ->helperText('Resize oversized uploads and re-compress, in addition to the security re-encode every upload already goes through.')
                            ->default(true)
                            ->live(),

                        Forms\Components\Toggle::make('image_convert_webp')
                            ->label('Convert to WebP')
                            ->helperText('Re-saves JPEG/PNG uploads as WebP (smaller files, near-universal browser support). GIFs are left untouched — GD would flatten an animation to one frame.')
                            ->default(true)
                            ->visible(fn (Get $get) => $get('optimize_images')),

                        Forms\Components\TextInput::make('image_quality')
                            ->label('Compression Quality')
                            ->helperText('1-100. Lower = smaller files, more visible compression. 82 is a strong default for photos and logos alike.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->default(82)
                            ->visible(fn (Get $get) => $get('optimize_images')),

                        Forms\Components\TextInput::make('image_max_width')
                            ->label('Max Width (px)')
                            ->helperText('A cap, not a target — smaller uploads are never upscaled.')
                            ->numeric()
                            ->minValue(100)
                            ->maxValue(10000)
                            ->required()
                            ->default(2000)
                            ->visible(fn (Get $get) => $get('optimize_images')),

                        Forms\Components\TextInput::make('image_max_height')
                            ->label('Max Height (px)')
                            ->numeric()
                            ->minValue(100)
                            ->maxValue(10000)
                            ->required()
                            ->default(2000)
                            ->visible(fn (Get $get) => $get('optimize_images')),
                    ])->columns(2),

                Section::make('Queue')
                    ->description('Queue worker retry behaviour for Redis-backed queues.')
                    ->schema([
                        Forms\Components\Placeholder::make('queue_retry_after_note')
                            ->label('')
                            ->content('Queue retry-after is set via the REDIS_QUEUE_RETRY_AFTER environment variable (config/queue.php), not this panel — queue connections are resolved from config at worker boot, before any database settings are available.'),
                    ]),
            ]);
    }
}
