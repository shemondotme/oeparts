<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\AdminUi;
use App\Services\SitemapService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class SeoSettings extends SettingsPage
{
    protected static ?string $title = 'SEO & Meta';

    protected static ?string $slug = 'seo-settings';

    protected static string $settingsGroup = 'seo';

    protected static ?int $navigationSort = 20;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),

            Action::make('regenerateSitemap')
                ->label('Regenerate sitemap now')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                // Mirrors SettingsPage::canAccess() (super_admin/admin) — there is no
                // dedicated "manage settings" permission, and custom actions get zero
                // automatic authorization (CLAUDE.md rule #31).
                ->authorize(fn (): bool => auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false)
                ->requiresConfirmation()
                ->modalDescription('Rebuilds sitemap.xml and every sub-sitemap (parts, brands, models, pages, blog) from current data. This can take a moment on a large catalog.')
                ->action(function (SitemapService $sitemaps): void {
                    try {
                        $files = $sitemaps->generateAll();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Sitemap regeneration failed')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Sitemap regenerated')
                        ->body(count($files).' file(s) written, last updated just now.')
                        ->success()
                        ->send();
                }),
        ];
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

                Section::make('SEO Directives & Crawling Defaults')
                    ->description('Configure search crawler policies and sitemap indexes.')
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

                        Forms\Components\Toggle::make('google_ping_enabled')
                            ->label('Ping Google on Sitemap Updates')
                            ->helperText('Automatically requests recrawling when products updates trigger new sitemap builds')
                            ->default(true),

                        Forms\Components\TextInput::make('twitter_handle')
                            ->label('Company Twitter Handle')
                            ->placeholder('@oeparts')
                            ->maxLength(50)
                            ->default(null),
                    ])->columns(2),

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
                    ])->columns(2),

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
            ]);
    }
}
