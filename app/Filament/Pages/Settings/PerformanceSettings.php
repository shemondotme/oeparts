<?php

namespace App\Filament\Pages\Settings;

use App\Services\CloudflareService;
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
            Action::make('testCloudflare')
                ->label('Test Cloudflare Connection')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function (CloudflareService $cloudflare) {
                    $result = $cloudflare->testConnection();

                    Notification::make()
                        ->title($result['success'] ? 'Cloudflare connected' : 'Cloudflare connection failed')
                        ->body($result['message'])
                        ->color($result['success'] ? 'success' : 'danger')
                        ->send();
                }),
            Action::make('purgeCloudflare')
                ->label('Purge Cloudflare Cache')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Purge Entire Cloudflare Cache')
                ->modalDescription('Removes every cached file at the Cloudflare edge for this zone. The next visitor to each page/asset re-fetches it from this server. Safe to do any time — never breaks anything, just costs a moment of extra origin load.')
                ->modalSubmitActionLabel('Purge Everything')
                ->action(function (CloudflareService $cloudflare) {
                    $result = $cloudflare->purgeEverything();

                    Notification::make()
                        ->title($result['success'] ? 'Cloudflare cache purged' : 'Purge failed')
                        ->body($result['message'])
                        ->color($result['success'] ? 'success' : 'danger')
                        ->send();
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

                        Forms\Components\Toggle::make('cache_conditions')
                            ->label('Cache Condition List (New/Used/Refurbished)')
                            ->helperText('Rarely-changing reference data read on every search-results page. Fixed 1-hour TTL.')
                            ->default(true),

                        Forms\Components\Toggle::make('cache_homepage_stats')
                            ->label('Cache Homepage Hero Stats')
                            ->helperText('Parts/manufacturer/cross-ref counts and the "INDEXED:" popular-OEM strip. Fixed 1-6 hour TTL.')
                            ->default(true),

                        Forms\Components\Toggle::make('cache_search_console_stats')
                            ->label('Cache Search Console Status Panel')
                            ->helperText('Active brand/product counts shown on the admin Search Console page — does not affect the storefront.')
                            ->default(true),
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

                Section::make('Page Loading')
                    ->description('How images already on the page are delivered to the browser — separate from Image Optimization above, which controls how uploads are stored.')
                    ->schema([
                        Forms\Components\Toggle::make('lazy_load_images')
                            ->label('Lazy-Load Below-the-Fold Images')
                            ->helperText('Defers loading images outside the initial viewport (brand logos, blog/manufacturer grid thumbnails) until the visitor scrolls near them. The first hero/featured image on any page always loads immediately regardless of this setting, to avoid delaying it.')
                            ->default(true),
                    ]),

                Section::make('Cloudflare CDN')
                    ->description('Purge-only integration — this panel never changes your live Cloudflare zone configuration, only tells it to drop cached copies when content changes. Configure the dashboard settings below directly on Cloudflare.')
                    ->schema([
                        Forms\Components\Toggle::make('cloudflare_enabled')
                            ->label('Enable Cloudflare Integration')
                            ->helperText('Turns on auto-purge (sitemap regeneration) and the Test/Purge actions above. Off by default — nothing changes until you turn this on and fill in both fields below.')
                            ->default(false)
                            ->live(),

                        Forms\Components\TextInput::make('cloudflare_zone_id')
                            ->label('Zone ID')
                            ->helperText('Cloudflare dashboard → your domain → Overview (right sidebar, "API" box).')
                            ->maxLength(255)
                            ->default(null)
                            ->visible(fn (Get $get) => $get('cloudflare_enabled')),

                        Forms\Components\TextInput::make('cloudflare_api_token')
                            ->label('API Token')
                            ->password()
                            ->revealable()
                            ->helperText('Saved encrypted in database. Create one at Cloudflare → My Profile → API Tokens, with "Zone → Cache Purge → Purge" permission scoped to this zone — never use your Global API Key here.')
                            ->default(null)
                            ->visible(fn (Get $get) => $get('cloudflare_enabled')),

                        Forms\Components\Placeholder::make('cloudflare_dashboard_note')
                            ->label('Recommended Cloudflare dashboard settings')
                            ->content('This app already sends correct Cache-Control headers for compiled assets and uploaded media (1 year, immutable) — Cloudflare respects those by default under Standard caching. Worth checking directly on Cloudflare\'s side: Speed → Optimization → Auto Minify (HTML/CSS/JS) and Brotli, both on; Caching → Browser Cache TTL → "Respect Existing Headers"; Caching → Always Online → on (serves a cached copy if this server is ever briefly unreachable).')
                            ->visible(fn (Get $get) => $get('cloudflare_enabled')),
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
