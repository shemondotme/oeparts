<?php

namespace App\Filament\Pages\Settings;

use App\Enums\SettingType;
use App\Filament\Resources\RedirectResource;
use App\Filament\Resources\SeoMetaResource;
use App\Filament\Support\AdminUi;
use App\Jobs\RegenerateSitemap;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CloudflareService;
use App\Services\SettingsService;
use App\Support\EncryptedSettingValue;
use App\Support\RobotsCustomRules;
use Database\Seeders\SettingsSeeder;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
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

            Action::make('viewSeoAudit')
                ->label('Run SEO Audit')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->outlined()
                ->url(SeoAudit::getUrl()),

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
        $warnings = $this->crawlabilityWarnings();
        $suffix = $warnings === [] ? '' : ' ⚠ '.implode(' ⚠ ', $warnings);

        if (! is_file($path)) {
            return 'Sitemap has not been generated yet.'.$suffix;
        }

        $total = array_sum($this->sitemapUrlCounts());

        return 'Sitemap last generated '.Carbon::createFromTimestamp(filemtime($path))->diffForHumans()
            ." — {$total} URLs across ".count($this->sitemapUrlCounts()).' files.'.$suffix;
    }

    /**
     * Misconfigurations that silently take a live site out of search — the
     * kind that show up as "traffic vanished" weeks later. Each is a
     * one-line plain-language warning for the page subheading.
     *
     * @return array<int, string>
     */
    private function crawlabilityWarnings(): array
    {
        $warnings = [];

        // robots.txt blocks every crawler outside APP_ENV=production. A live
        // site whose .env still says local/staging is invisible to Google.
        // (A deliberate staging/quarantine copy is handled by SafeMode, which
        // is expected to block.)
        $isCopy = app(\App\Services\Instance\SafeMode::class)->isCopy();
        if (! $isCopy && ! app()->environment('production')) {
            $warnings[] = 'APP_ENV is "'.app()->environment().'", not "production": robots.txt currently tells every search engine to stay away (Disallow: /). Set APP_ENV=production in .env on the live site.';
        }

        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if (in_array($host, ['', 'localhost', '127.0.0.1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            $warnings[] = 'APP_URL points at a local address ('.config('app.url').'): sitemap, robots.txt and emails will advertise it. Set APP_URL to the live https:// domain.';
        }

        $sitemap = public_path('sitemap.xml');
        if (is_file($sitemap) && preg_match('#<loc>https?://(localhost|127\.0\.0\.1)#i', (string) @file_get_contents($sitemap, false, null, 0, 4096)) === 1) {
            $warnings[] = 'The generated sitemap.xml lists localhost URLs — fix APP_URL, then regenerate the sitemap.';
        }

        return $warnings;
    }

    /**
     * A silently truncated or empty regeneration was previously
     * unnoticeable without opening the raw XML — an admin had only a
     * last-generated timestamp to go on. base name => <loc> count, one
     * entry per sub-sitemap that actually exists on disk (batched files
     * like sitemap-parts-2.xml are summed into their base's count).
     *
     * @return array<string, int>
     */
    private function sitemapUrlCounts(): array
    {
        $bases = ['sitemap-parts', 'sitemap-crossrefs', 'sitemap-brands', 'sitemap-models', 'sitemap-pages', 'sitemap-blog'];
        $counts = [];

        foreach ($bases as $base) {
            $files = glob(public_path("sitemaps/{$base}*.xml")) ?: [];
            $count = 0;
            foreach ($files as $file) {
                $contents = @file_get_contents($file);
                $count += $contents ? substr_count($contents, '<loc>') : 0;
            }
            if ($files !== []) {
                $counts[$base] = $count;
            }
        }

        return $counts;
    }

    private function sitemapUrlCountsHtml(): string
    {
        $counts = $this->sitemapUrlCounts();

        if ($counts === []) {
            return '<p class="text-sm text-gray-500">No sitemap files found yet — regenerate above.</p>';
        }

        $labels = [
            'sitemap-parts' => 'Parts', 'sitemap-crossrefs' => 'Cross-references', 'sitemap-brands' => 'Brands',
            'sitemap-models' => 'Car models', 'sitemap-pages' => 'Pages', 'sitemap-blog' => 'Blog posts',
        ];

        $items = [];
        foreach ($counts as $base => $count) {
            $items[] = '<span class="text-gray-600 dark:text-gray-400">'.e($labels[$base] ?? $base).': <strong>'.number_format($count).'</strong></span>';
        }

        $sitemapUrl = e(url('sitemap.xml'));

        return '<div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">'.implode('', $items)
            .' &middot; <a href="'.$sitemapUrl.'" target="_blank" rel="noopener" class="fi-link text-primary-600">View sitemap.xml &#8599;</a></div>';
    }

    private ?Product $samplePreviewProductCache = null;

    private bool $samplePreviewProductResolved = false;

    /**
     * The one real active product this Control Center previews templates
     * against — save-and-check-the-live-page was the only feedback loop
     * before this. Memoized on the instance (NOT a `static` local — a
     * Livewire component instance is reconstructed fresh per request, but
     * a `static` variable inside a method would persist for the whole PHP
     * worker's lifetime, caching a product that's since changed or been
     * deleted across totally unrelated requests). A fresh install/empty
     * catalog falls back to illustrative dummy values rather than showing
     * nothing.
     */
    private function samplePreviewProduct(): ?Product
    {
        if (! $this->samplePreviewProductResolved) {
            $this->samplePreviewProductCache = Product::query()->with('manufacturer')->where('is_active', true)->first();
            $this->samplePreviewProductResolved = true;
        }

        return $this->samplePreviewProductCache;
    }

    /**
     * Same {oem}/{count}/{site}/{min}/{max}/{manufacturer}/{brand}
     * placeholder vocabulary already documented in this page's own helper
     * text (and rendered for real in results.blade.php/manufacturer/show.
     * blade.php) — kept self-contained here rather than extracted into a
     * shared renderer, since this is read-only preview text, not the real
     * rendering path those views own.
     */
    private function renderTemplatePreview(?string $template): string
    {
        $template = trim((string) $template);
        if ($template === '') {
            return '(nothing typed yet — falls back to the built-in default)';
        }

        $product = $this->samplePreviewProduct();
        $manufacturerName = $product?->manufacturer ? trans_field($product->manufacturer->name) : 'Bosch';

        return str_replace(
            ['{oem}', '{count}', '{site}', '{min}', '{max}', '{manufacturer}', '{brand}', '{model}', '{years}'],
            [
                $product?->oem_number ?? 'ABC12345',
                '3',
                settings('general.site_name', 'OeParts'),
                $product ? (string) $product->price : '49.99',
                $product ? (string) $product->price : '89.99',
                $manufacturerName,
                $manufacturerName,
                'Golf',
                '2015–2022',
            ],
            $template
        );
    }

    private function templatePreviewPlaceholder(string $key, string $fieldPath, string $label): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make($key)
            ->label($label)
            ->content(fn (Get $get) => new HtmlString(
                '<span class="font-mono text-xs text-gray-600 dark:text-gray-400">'.e($this->renderTemplatePreview($get($fieldPath))).'</span>'
            ));
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
                                'live' => true,
                            ],
                            'home_description' => [
                                'label' => 'Homepage Meta Description',
                                'type' => 'textarea',
                                'rows' => 2,
                                'maxLength' => 160,
                                'helperText' => 'Ideal: 150-160 characters',
                                'live' => true,
                            ],
                            'brand_title_template' => [
                                'label' => 'Brand/Manufacturer Page Title Template',
                                'maxLength' => 100,
                                'helperText' => 'Available placeholders: {brand}',
                                'live' => true,
                            ],
                        ]),
                        $this->templatePreviewPlaceholder('home_title_preview', 'home_title.en', 'Homepage Title Preview (English)'),
                        $this->templatePreviewPlaceholder('home_description_preview', 'home_description.en', 'Homepage Description Preview (English)'),
                        $this->templatePreviewPlaceholder('brand_title_template_preview', 'brand_title_template.en', 'Brand Title Preview (English, sample brand)'),
                    ]),

                Section::make('Brand & Car-Model Page Templates')
                    ->description('Default description for brand pages and default title + description for car-model pages (the page-by-page text is set when editing a brand or model, and wins over these). Leave a language empty to keep the built-in wording. Placeholders are not translated.')
                    ->collapsible()
                    ->schema([
                        AdminUi::translatableTabs('Brand & Model Meta Locales', [
                            'brand_description_template' => [
                                'label' => 'Brand Page Meta Description Template',
                                'type' => 'textarea',
                                'rows' => 2,
                                'required' => false,
                                'maxLength' => 200,
                                'helperText' => 'Placeholders: {brand}, {count}, {site}. e.g. "Genuine {brand} OEM parts: {count} part numbers, verified EU suppliers. {site}"',
                                'live' => true,
                            ],
                            'model_title_template' => [
                                'label' => 'Car-Model Page Title Template',
                                'required' => false,
                                'maxLength' => 100,
                                'helperText' => 'Placeholders: {model}, {brand}, {years}, {site}. e.g. "{brand} {model} OEM Parts ({years}) | {site}"',
                                'live' => true,
                            ],
                            'model_description_template' => [
                                'label' => 'Car-Model Page Meta Description Template',
                                'type' => 'textarea',
                                'rows' => 2,
                                'required' => false,
                                'maxLength' => 200,
                                'helperText' => 'Placeholders: {model}, {brand}, {years}, {count}, {site}.',
                                'live' => true,
                            ],
                        ]),
                        $this->templatePreviewPlaceholder('brand_description_template_preview', 'brand_description_template.en', 'Brand Description Preview (English, sample brand)'),
                        $this->templatePreviewPlaceholder('model_title_template_preview', 'model_title_template.en', 'Car-Model Title Preview (English, sample model)'),
                        $this->templatePreviewPlaceholder('model_description_template_preview', 'model_description_template.en', 'Car-Model Description Preview (English, sample model)'),
                    ]),

                Section::make('SEO Audit Alerts')
                    ->description('The SEO Audit runs every morning. When it finds a CRITICAL problem — one that can keep the site or a whole section out of search, like a wrong environment setting or a "noindex" default — the admins get a notification and an email. Each problem is announced once, with a reminder after a week if it is still open.')
                    ->schema([
                        Forms\Components\Toggle::make('audit_alerts_enabled')
                            ->label('Alert the admins about critical SEO problems')
                            ->default(true),
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
                                'live' => true,
                            ],
                            'search_results_meta_template' => [
                                'label' => 'Search Results Meta Description Template',
                                'type' => 'textarea',
                                'rows' => 2,
                                'maxLength' => 200,
                                'helperText' => 'Placeholders: {oem}, {count}, {site}, {min}, {max}, {manufacturer}, {brand}. e.g. "Genuine OEM part {oem}, from €{min}, on {site}."',
                                'live' => true,
                            ],
                        ]),
                        $this->templatePreviewPlaceholder('search_results_title_template_preview', 'search_results_title_template.en', 'Title Preview (English, sample product)'),
                        $this->templatePreviewPlaceholder('search_results_meta_template_preview', 'search_results_meta_template.en', 'Meta Description Preview (English, sample product)'),
                    ]),

                Section::make('Parts Search Console Templates')
                    ->description('Title and meta description for the Parts Search Console (/{lang}/parts) — the search landing page, which IS indexed (unlike the noindex zero-results page).')
                    ->schema([
                        AdminUi::translatableTabs('Search Console Meta Locales', [
                            'console_title_template' => [
                                'label' => 'Console Title Template',
                                'maxLength' => 100,
                                'helperText' => 'Placeholders: {site}. e.g. "Parts Search Console | {site}"',
                                'live' => true,
                            ],
                            'console_meta_template' => [
                                'label' => 'Console Meta Description Template',
                                'type' => 'textarea',
                                'rows' => 2,
                                'maxLength' => 200,
                                'helperText' => 'Placeholders: {site}.',
                                'live' => true,
                            ],
                        ]),
                        $this->templatePreviewPlaceholder('console_title_template_preview', 'console_title_template.en', 'Title Preview (English)'),
                        $this->templatePreviewPlaceholder('console_meta_template_preview', 'console_meta_template.en', 'Meta Description Preview (English)'),
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

                Section::make('Structured Data Testing')
                    ->description('No in-admin JSON-LD preview exists yet — these open Google\'s own validators pre-filled with the homepage. Paste any product/page URL once there to test it instead.')
                    ->schema([
                        Forms\Components\Placeholder::make('structured_data_test_links')
                            ->label('')
                            ->columnSpanFull()
                            ->content(fn () => new HtmlString(
                                '<div class="flex flex-wrap gap-4 text-sm">'
                                .'<a href="https://search.google.com/test/rich-results?url='.urlencode(url('/')).'" target="_blank" rel="noopener" class="fi-link text-primary-600">Google Rich Results Test &#8599;</a>'
                                .'<a href="https://validator.schema.org/#url='.urlencode(url('/')).'" target="_blank" rel="noopener" class="fi-link text-primary-600">Schema.org Markup Validator &#8599;</a>'
                                .'</div>'
                            )),
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
                        Forms\Components\Textarea::make('default_description')
                            ->label('Default Meta Description')
                            ->rows(2)
                            ->maxLength(160)
                            ->helperText('Fallback <meta name="description"> for any page with no page-specific description and no Advanced SEO override set (layouts/app.blade.php). Ideal: 150-160 characters.'),
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

                Section::make('Additional robots.txt Rules')
                    ->description('Extra lines added to the "all crawlers" (*) group of robots.txt — for example to keep a section out of search. The standard rules (admin, cart, checkout, filters, …) stay in place. Only applied in production.')
                    ->schema([
                        Forms\Components\Textarea::make('robots_custom_rules')
                            ->label('Extra Rules')
                            ->rows(6)
                            ->placeholder("# keep an internal area out of search\nDisallow: /en/internal-tools/")
                            ->helperText('One rule per line: Disallow: /path, Allow: /path, Crawl-delay: seconds, or Sitemap: https://… . A bare "Disallow: /" is refused (it would block the whole site). Lines starting with # are comments.')
                            ->rules([
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                    if (($problem = RobotsCustomRules::problem((string) $value)) !== null) {
                                        $fail($problem);
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('robots_live_link')
                            ->label('')
                            ->content(new HtmlString('<a href="'.e(url('/robots.txt')).'" target="_blank" rel="noopener" class="fi-link text-primary-600">Open the live robots.txt &rarr;</a>'))
                            ->columnSpanFull(),
                    ]),

                Section::make('IndexNow')
                    ->description('Pushes changed URLs (products, pages, posts, brands, models) to Bing/Yandex/Naver/Seznam for near-instant re-crawl on every save. Google does NOT support IndexNow (a common misconception) — Google is told about the sitemap through Search Console (Sitemap & Indexing tab).')
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
                Section::make('Search Engine Notification')
                    ->description('Google and Bing retired their anonymous sitemap "ping" URLs. Google is now told through the Search Console API (needs the Search Console credentials on the Integrations tab, with the read/write scope); Bing and other engines are told per URL through IndexNow.')
                    ->schema([
                        Forms\Components\Toggle::make('google_ping_enabled')
                            ->label('Submit sitemap to Google Search Console on regeneration')
                            ->default(true),

                        Actions::make([$this->regenerateSitemapAction()])
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('sitemap_url_counts')
                            ->label('')
                            ->columnSpanFull()
                            ->content(fn () => new HtmlString($this->sitemapUrlCountsHtml())),
                    ])->columns(2),

                Section::make('What the Sitemap Contains')
                    ->description('Switch whole groups of URLs in or out of the sitemap. A switched-off group is left out the next time the sitemap is generated (and its old files are removed). Individual pages and posts can be excluded by setting them to "No Index" in their own SEO settings.')
                    ->schema([
                        Forms\Components\Toggle::make('sitemap_include_products')
                            ->label('Parts (OEM search / product pages)')
                            ->default(true),
                        Forms\Components\Toggle::make('sitemap_include_crossrefs')
                            ->label('Cross-reference numbers')
                            ->default(true),
                        Forms\Components\Toggle::make('sitemap_include_brands')
                            ->label('Brand pages')
                            ->default(true),
                        Forms\Components\Toggle::make('sitemap_include_models')
                            ->label('Car-model pages')
                            ->default(true),
                        Forms\Components\Toggle::make('sitemap_include_pages')
                            ->label('CMS pages and homepages')
                            ->default(true),
                        Forms\Components\Toggle::make('sitemap_include_blog')
                            ->label('Blog posts')
                            ->default(true),
                    ])->columns(2),

                Section::make('Canonical Host')
                    ->description('Forces every request onto a single host (e.g. redirects www to the bare domain, or vice versa). Leave blank to opt out entirely — recommended until DNS/SSL for the canonical host is fully set up.')
                    ->schema([
                        Forms\Components\TextInput::make('canonical_host')
                            ->label('Canonical Host')
                            ->placeholder('oeparts.com')
                            ->helperText('Only the host name, for example oeparts.com. No https://, no path, no port. The admin panel and the installer are never redirected by this setting.')
                            ->maxLength(255)
                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? strtolower(trim($state)) : $state)
                            ->regex('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i')
                            ->validationMessages(['regex' => 'Enter only the host name, for example oeparts.com: no https://, no path, no port.'])
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
                                .RedirectResource::getUrl()
                                .'" class="fi-link text-primary-600">Redirects</a> page.'
                            )),

                        Forms\Components\Placeholder::make('seo_meta_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Per-page title/description overrides for individual products, categories, and manufacturers are managed on the <a href="'
                                .SeoMetaResource::getUrl()
                                .'" class="fi-link text-primary-600">SEO Meta</a> page.'
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
        $unreadable = [];
        foreach ($settings as $setting) {
            $value = $setting->value;

            if ($setting->is_encrypted && $value) {
                // An unreadable secret is shown empty, never as ciphertext.
                $revealed = EncryptedSettingValue::reveal($value, $setting->key);
                $value = $revealed->value;

                if (! $revealed->ok) {
                    $unreadable[] = $setting->key;
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

        EncryptedSettingValue::warnUnreadable($unreadable);
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
