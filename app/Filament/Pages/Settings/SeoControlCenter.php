<?php

namespace App\Filament\Pages\Settings;

use App\Enums\SettingType;
use App\Filament\Support\AdminUi;
use App\Jobs\RegenerateSitemap;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\CloudflareService;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Yoast/Rank Math-style consolidated SEO settings — absorbs the old
 * SeoSettings page in full (same registry key 'seo-settings', so no
 * dangling nav entry) plus every setting introduced across the SEO
 * program: per-product detail pages, IndexNow, per-bot AI-crawler rules,
 * llms.txt, Bing ping.
 *
 * Spans TWO settings groups ('seo' and 'crawlers') — SettingsPage's base
 * implementation assumes a single static::$settingsGroup throughout
 * (fillForm/save/persistChanges/resetToDefaults/getFactoryDefaults/
 * getEncryptedKeys all query by that one group), so every one of those is
 * overridden here to loop over $settingsGroups instead. Field names must
 * stay globally unique across every managed group — confirmed no overlap
 * between 'seo' and 'crawlers' keys, since $this->data is a flat
 * key=>value array with no group prefix.
 */
class SeoControlCenter extends SettingsPage
{
    protected static ?string $title = 'SEO Control Center';

    protected static ?string $slug = 'seo-settings';

    protected static string $settingsGroup = 'seo';

    /** @var string[] */
    protected static array $settingsGroups = ['seo', 'crawlers'];

    protected static ?int $navigationSort = 20;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),

            Action::make('viewHealthDashboard')
                ->label('View SEO Health Dashboard')
                ->icon('heroicon-o-chart-bar-square')
                ->color('gray')
                ->outlined()
                ->url(SeoHealthDashboard::getUrl()),
        ];
    }

    // regenerateSitemap moved inline into sitemapTab()'s "Search Engine
    // Pings" section — it was a page-level header action (rendering on
    // every SEO tab, not just Sitemap & Indexing) despite being sitemap-
    // specific. viewHealthDashboard above stays in the header: it links to
    // a dashboard aggregating every SEO tab, not just one.
    public function regenerateSitemapAction(): Action
    {
        return Action::make('regenerateSitemap')
            ->label('Regenerate sitemap now')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->authorize(fn (): bool => auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false)
            ->requiresConfirmation()
            ->modalDescription('Rebuilds sitemap.xml and every sub-sitemap (parts, cross-references, brands, models, pages, blog) from current data. This can take a moment on a large catalog.')
            ->action(function (): void {
                try {
                    RegenerateSitemap::dispatch(auth('admin')->user()?->name ?? 'An admin');
                } catch (\Throwable $e) {
                    Notification::make()->title('Sitemap regeneration failed')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Sitemap regeneration requested')
                    ->body("You'll get a notification here (the bell icon) once it finishes.")
                    ->success()
                    ->send();
            });
    }

    public function getSubheading(): ?string
    {
        $path = public_path('sitemap.xml');

        if (! is_file($path)) {
            return 'Sitemap has not been generated yet.';
        }

        return 'Sitemap last generated '.\Illuminate\Support\Carbon::createFromTimestamp(filemtime($path))->diffForHumans().'.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('SEO Control Center')
                    ->columnSpanFull()
                    ->activeTab(fn (): int => (int) request()->query('tab', 1))
                    ->tabs([
                        $this->generalTab(),
                        $this->searchResultsTab(),
                        $this->structuredDataTab(),
                        $this->crawlersTab(),
                        $this->sitemapTab(),
                        $this->socialTab(),
                    ]),
            ]);
    }

    private function generalTab(): Tab
    {
        return Tab::make('General')
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Section::make('Global Meta Title Templates')
                    ->description('Placeholders like {oem}, {min}, {max}, {manufacturer}, or {brand} are evaluated dynamically and are NOT translated — keep them as literal tokens in every locale tab.')
                    ->schema([
                        AdminUi::translatableTabs('Homepage & Brand Meta Locales', [
                            'home_title' => [
                                'label' => 'Homepage Meta Title',
                                'required' => true,
                                'maxLength' => 60,
                                'helperText' => 'Ideal: 50-60 characters',
                            ],
                            'home_description' => [
                                'label' => 'Homepage Meta Description',
                                'type' => 'textarea',
                                'rows' => 2,
                                'maxLength' => 160,
                                'helperText' => 'Ideal: 150-160 characters',
                            ],
                            'brand_title_template' => [
                                'label' => 'Brand/Manufacturer Page Title Template',
                                'maxLength' => 100,
                                'helperText' => 'Available placeholders: {brand}',
                            ],
                        ]),
                    ]),

                Section::make('Per-Product Detail Pages')
                    ->description('Controls whether every product also gets its own detail page (/parts/{oem}/{id}-slug), or the OEM search results page handles everything on its own.')
                    ->schema([
                        Forms\Components\Toggle::make('detail_pages_enabled')
                            ->label('Enable per-product detail pages')
                            ->helperText('When off, /parts/{oem}/... URLs 301 back to the OEM results page — existing indexed links are never left as dead ends, regardless of which way this is toggled.')
                            ->default(false),
                    ]),
            ]);
    }

    private function searchResultsTab(): Tab
    {
        return Tab::make('Search Results')
            ->icon('heroicon-o-magnifying-glass')
            ->schema([
                Section::make('Search Results Templates')
                    ->description('Customise the title and meta description shown on internal search result pages — including individual OEM part pages (/parts/{oem}), which are rendered by the search results page. Placeholders are not translated.')
                    ->schema([
                        AdminUi::translatableTabs('Search Results Meta Locales', [
                            'search_results_title_template' => [
                                'label' => 'Search Results Title Template',
                                'maxLength' => 100,
                                'helperText' => 'Placeholders: {oem}, {count}, {site}, {min}, {max}, {manufacturer}, {brand}. e.g. "Buy OEM Part {oem} — From €{min} | {site}"',
                            ],
                            'search_results_meta_template' => [
                                'label' => 'Search Results Meta Description Template',
                                'type' => 'textarea',
                                'rows' => 2,
                                'maxLength' => 200,
                                'helperText' => 'Placeholders: {oem}, {count}, {site}, {min}, {max}, {manufacturer}, {brand}. e.g. "Genuine OEM part {oem}, from €{min}, on {site}."',
                            ],
                        ]),
                    ]),

                Section::make('Parts Search Console Templates')
                    ->description('Title and meta description for the Parts Search Console (/{lang}/parts) — the search landing page, which IS indexed (unlike the noindex zero-results page).')
                    ->schema([
                        AdminUi::translatableTabs('Search Console Meta Locales', [
                            'console_title_template' => [
                                'label' => 'Console Title Template',
                                'maxLength' => 100,
                                'helperText' => 'Placeholders: {site}. e.g. "Parts Search Console | {site}"',
                            ],
                            'console_meta_template' => [
                                'label' => 'Console Meta Description Template',
                                'type' => 'textarea',
                                'rows' => 2,
                                'maxLength' => 200,
                                'helperText' => 'Placeholders: {site}.',
                            ],
                        ]),
                    ]),

                Section::make('Fallback Product Description')
                    ->description('Used only when a product has no manually-written description. Composed from real per-product facts (fitment, delivery, MOQ, condition, cross-reference count) — genuinely blank placeholders render as empty text, not a broken sentence.')
                    ->schema([
                        AdminUi::translatableTabs('Fallback Description Template Locales', [
                            'auto_description_template' => [
                                'label' => 'Fallback Description Template',
                                'type' => 'textarea',
                                'rows' => 3,
                                'maxLength' => 500,
                                'helperText' => 'Placeholders: {manufacturer}, {condition}, {oem}, {fitment}, {delivery}, {moq}, {cross_ref_count}.',
                            ],
                        ]),
                    ]),
            ]);
    }

    private function structuredDataTab(): Tab
    {
        return Tab::make('Structured Data')
            ->icon('heroicon-o-code-bracket')
            ->schema([
                Section::make('Product Condition Mapping')
                    ->description('How each catalog Condition maps to the schema.org ItemCondition value used in Product structured data. This mapping is code, not an editable setting — shown here for visibility. An unmapped condition simply omits the field rather than guessing.')
                    ->schema([
                        Forms\Components\Placeholder::make('condition_map_display')
                            ->label('')
                            ->content(view('components.seo.condition-schema-map')),
                    ]),

                Section::make('Webmaster Verification Codes')
                    ->description('Verify website ownership with search console integrations.')
                    ->schema([
                        Forms\Components\TextInput::make('google_verification')
                            ->label('Google Site Verification Key')
                            ->maxLength(255)
                            ->placeholder('google-verification-token')
                            ->default(null),

                        Forms\Components\TextInput::make('bing_verification')
                            ->label('Bing Site Verification Key')
                            ->maxLength(255)
                            ->placeholder('bing-verification-token')
                            ->default(null),
                    ])->columns(2),

                Section::make('Health Dashboard: Google Search Console')
                    ->description('Powers the Health Dashboard\'s indexed/submitted/errors widget. Needs a Google Cloud OAuth client and a one-time authorization to obtain a refresh token — both happen outside this admin panel, in Google Cloud Console / the OAuth consent screen.')
                    ->schema([
                        Forms\Components\TextInput::make('gsc_property_url')
                            ->label('Search Console Property URL')
                            ->placeholder('https://oeparts.com/')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('gsc_client_id')
                            ->label('OAuth Client ID')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('gsc_client_secret')
                            ->label('OAuth Client Secret')
                            ->maxLength(255)
                            ->password()
                            ->revealable()
                            ->default(null),
                        Forms\Components\TextInput::make('gsc_refresh_token')
                            ->label('OAuth Refresh Token')
                            ->maxLength(1000)
                            ->password()
                            ->revealable()
                            ->default(null),
                    ])->columns(2),

                Section::make('Health Dashboard: Core Web Vitals')
                    ->description('Powers the Health Dashboard\'s real-user LCP/CLS/INP widget via the free Chrome UX Report (CrUX) API — needs a Google Cloud API key with the Chrome UX Report API enabled, no OAuth required.')
                    ->schema([
                        Forms\Components\TextInput::make('crux_api_key')
                            ->label('CrUX API Key')
                            ->maxLength(255)
                            ->password()
                            ->revealable()
                            ->default(null),
                    ]),
            ]);
    }

    private function crawlersTab(): Tab
    {
        return Tab::make('Crawlers & AI')
            ->icon('heroicon-o-cpu-chip')
            ->schema([
                Section::make('SEO Directives & Crawling Defaults')
                    ->description('Configure search crawler policies.')
                    ->schema([
                        Forms\Components\Select::make('default_robots')
                            ->label('Default Robots Directive')
                            ->options([
                                'index,follow' => 'Index, Follow (Recommended)',
                                'noindex,follow' => 'No Index, Follow',
                                'index,nofollow' => 'Index, No Follow',
                                'noindex,nofollow' => 'No Index, No Follow',
                            ])
                            ->default('index,follow'),
                    ]),

                Section::make('AI Crawler Policy')
                    ->description('Per-bot allow/block rules for robots.txt — a bot matching its own explicit rule ignores the wildcard rule entirely, so blocking one specific bot never affects the rest. Only applied in production.')
                    ->schema([
                        Forms\Components\Repeater::make('ai_bot_rules')
                            ->label('Bot Rules')
                            ->schema([
                                Forms\Components\TextInput::make('user_agent')
                                    ->label('User-Agent')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('GPTBot'),
                                Forms\Components\Select::make('action')
                                    ->label('Action')
                                    ->options(['allow' => 'Allow', 'block' => 'Block'])
                                    ->default('allow')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add bot rule')
                            ->reorderable(false)
                            ->default([]),
                    ]),

                Section::make('IndexNow')
                    ->description('Pushes changed product URLs to Bing/Yandex/Naver/Seznam for near-instant re-crawl on every save. Google does NOT support IndexNow (a common misconception) — Google indexing is handled by the sitemap + ping below.')
                    ->schema([
                        Forms\Components\Toggle::make('indexnow_enabled')
                            ->label('Enable IndexNow')
                            ->default(false),
                        Forms\Components\TextInput::make('indexnow_api_key')
                            ->label('IndexNow API Key')
                            ->maxLength(255)
                            // The verification route (/{key}.txt) only
                            // matches [a-zA-Z0-9]+ — a hyphenated key (a
                            // very common shape for a generated key/GUID)
                            // would save here without error but the
                            // verification file could then never actually
                            // be served, permanently and invisibly failing
                            // Bing's key-verification crawl.
                            ->regex('/^[a-zA-Z0-9]+$/')
                            ->helperText('Generate one at indexnow.org — the key must be reachable at /{key}.txt on this domain (handled automatically once set here). Letters and digits only, no hyphens.')
                            ->validationMessages([
                                'regex' => 'The IndexNow key may only contain letters and digits (no hyphens or other characters) — the verification file can only be served at that shape of URL.',
                            ])
                            ->password()
                            ->revealable(),
                    ])->columns(2),

                Section::make('llms.txt')
                    ->description('An emerging, still-unofficial convention giving AI crawlers/answer engines a short curated overview of the site, served at /llms.txt.')
                    ->schema([
                        AdminUi::translatableTabs('llms.txt Body Locales', [
                            'llms_txt_body' => [
                                'label' => 'llms.txt Body',
                                'type' => 'textarea',
                                'rows' => 6,
                                'maxLength' => 2000,
                                'helperText' => 'Placeholder: {site_url}.',
                            ],
                        ]),
                    ]),
            ]);
    }

    private function sitemapTab(): Tab
    {
        return Tab::make('Sitemap & Indexing')
            ->icon('heroicon-o-map')
            ->schema([
                Section::make('Search Engine Pings')
                    ->description('Automatically notify search engines when the sitemap regenerates.')
                    ->schema([
                        Forms\Components\Toggle::make('google_ping_enabled')
                            ->label('Ping Google on Sitemap Updates')
                            ->default(true),
                        Forms\Components\Toggle::make('bing_ping_enabled')
                            ->label('Ping Bing on Sitemap Updates')
                            ->default(true),

                        Actions::make([$this->regenerateSitemapAction()])
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Canonical Host')
                    ->description('Forces every request onto a single host (e.g. redirects www to the bare domain, or vice versa). Leave blank to opt out entirely — recommended until DNS/SSL for the canonical host is fully set up.')
                    ->schema([
                        Forms\Components\TextInput::make('canonical_host')
                            ->label('Canonical Host')
                            ->placeholder('oeparts.com')
                            ->maxLength(255)
                            ->default(null),
                    ]),

                Section::make('Related Tools')
                    ->description('Per-URL SEO overrides and 301 redirects are managed as data, not settings.')
                    ->schema([
                        Forms\Components\Placeholder::make('redirects_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                '301 redirects for retired/moved URLs are managed on the <a href="'
                                . \App\Filament\Resources\RedirectResource::getUrl()
                                . '" class="fi-link text-primary-600">Redirects</a> page.'
                            )),

                        Forms\Components\Placeholder::make('seo_meta_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Per-page title/description overrides for individual products, categories, and manufacturers are managed on the <a href="'
                                . \App\Filament\Resources\SeoMetaResource::getUrl()
                                . '" class="fi-link text-primary-600">SEO Meta</a> page.'
                            )),
                    ]),
            ]);
    }

    private function socialTab(): Tab
    {
        return Tab::make('Social')
            ->icon('heroicon-o-share')
            ->schema([
                Section::make('Open Graph Defaults')
                    ->description('Default meta shares rendered across social feeds (Facebook, LinkedIn, Discord). Site name is taken automatically from General Settings.')
                    ->schema([
                        Forms\Components\FileUpload::make('default_og_image')
                            ->label('Default OG Fallback Image')
                            ->image()
                            ->disk('public')
                            ->directory('og-images')
                            ->maxSize(1024)
                            ->helperText('Image shown when sharing links lacking specific graphics (max size 1MB)')
                            ->visibility('public'),

                        Forms\Components\TextInput::make('twitter_handle')
                            ->label('Company Twitter Handle')
                            ->placeholder('@oeparts')
                            ->maxLength(50)
                            ->default(null),
                    ])->columns(2),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Multi-group overrides — SettingsPage's base implementation assumes a
    // single static::$settingsGroup throughout; every method below loops
    // over static::$settingsGroups instead. See class docblock.
    // ─────────────────────────────────────────────────────────────────────

    protected function fillForm(): void
    {
        $settings = Setting::whereIn('group', static::$settingsGroups)->get(['key', 'value', 'is_encrypted']);

        $data = [];
        foreach ($settings as $setting) {
            $value = $setting->value;

            if ($setting->is_encrypted && $value) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    Log::warning("Failed to decrypt setting {$setting->key}: ".$e->getMessage());
                }
            }

            if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            $data[$setting->key] = $value;
        }

        $this->form->fill($data);
    }

    public function save(): void
    {
        $this->validate();

        $oldValues = Setting::whereIn('group', static::$settingsGroups)->pluck('value', 'key')->toArray();
        $changed = $this->buildChangesDiff($oldValues);

        if (empty($changed)) {
            Notification::make()->title('No changes detected')->info()->send();

            return;
        }

        $this->persistChanges($oldValues);

        Notification::make()
            ->title('Settings saved')
            ->body('Cache cleared for: '.implode(', ', static::$settingsGroups))
            ->success()
            ->send();
    }

    /** Maps a flat form field name to the settings group it actually belongs to. */
    private function groupForKey(string $key): string
    {
        static $map = null;

        if ($map === null) {
            $map = Setting::whereIn('group', static::$settingsGroups)->pluck('group', 'key')->all();
        }

        return $map[$key] ?? static::$settingsGroups[0];
    }

    private function persistChanges(array $oldValues): void
    {
        $service = app(SettingsService::class);
        $admin = auth('admin')->user();

        foreach ($this->data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (is_array($value)) {
                $value = empty($value) ? '' : json_encode($value);
            }

            $service->set($this->groupForKey($key).'.'.$key, $value);

            if (array_key_exists($key, $oldValues) && (string) ($oldValues[$key] ?? '') !== (string) $value) {
                $oldValues[$key] = '***';
            }
        }

        if ($admin) {
            $encryptedKeys = $this->getEncryptedKeys();
            ActivityLog::create([
                'admin_id' => $admin->id,
                'action' => $this->resetMode ? 'settings_reset' : 'settings_updated',
                'model_type' => Setting::class,
                'model_id' => null,
                'old_values' => array_intersect_key($oldValues, $this->data),
                'new_values' => collect($this->data)
                    ->mapWithKeys(fn ($v, $k) => [$k => in_array($k, $encryptedKeys) && $v ? '***' : $v])
                    ->toArray(),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        }

        $this->resetMode = false;
        $this->pendingChanges = null;
        $this->rememberData();

        $this->afterSave();
    }

    /**
     * Purges the CDN cache for robots.txt/llms.txt whenever this save
     * touched the 'crawlers' group — closes the "stale bot rules for up
     * to 5 minutes" gap down to near-immediate. Best-effort: a failure
     * here must never undo/mask the settings save that already succeeded.
     */
    protected function afterSave(): void
    {
        $crawlersKeys = Setting::where('group', 'crawlers')->pluck('key')->all();
        $savedCrawlersKeys = array_intersect(array_keys($this->data), $crawlersKeys);

        if (empty($savedCrawlersKeys)) {
            return;
        }

        try {
            app(CloudflareService::class)->purgeUrls([
                url('robots.txt'),
                url('llms.txt'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cloudflare robots.txt/llms.txt purge failed', ['error' => $e->getMessage()]);
        }
    }

    public function resetToDefaults(): void
    {
        $defaults = $this->getFactoryDefaults();

        if (empty($defaults)) {
            Notification::make()->title('No factory defaults defined')->warning()->send();

            return;
        }

        $oldValues = Setting::whereIn('group', static::$settingsGroups)->pluck('value', 'key')->toArray();
        $changed = $this->buildDiffBetween($oldValues, $defaults);

        if (empty($changed)) {
            Notification::make()->title('Settings already at defaults')->info()->send();

            return;
        }

        $this->pendingChanges = [
            'oldValues' => $oldValues,
            'changed' => $changed,
            'resetDefaults' => $defaults,
        ];
        $this->resetMode = true;
    }

    protected function getFactoryDefaults(): array
    {
        return collect(SettingsSeeder::definitions())
            ->whereIn('group', static::$settingsGroups)
            ->mapWithKeys(fn (array $row) => [$row['key'] => $this->castDefinitionValue($row['value'], $row['type'])])
            ->all();
    }

    private function castDefinitionValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            SettingType::Boolean->value => in_array($value, ['1', 1, true], true),
            SettingType::Integer->value => (int) $value,
            SettingType::Decimal->value => (float) $value,
            SettingType::Json->value => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    protected function getEncryptedKeys(): array
    {
        return Setting::whereIn('group', static::$settingsGroups)
            ->where('is_encrypted', true)
            ->pluck('key')
            ->toArray();
    }
}
