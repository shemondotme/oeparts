<?php

namespace App\Filament\Pages\Settings;

use App\Enums\SettingType;
use App\Filament\Support\AdminUi;
use App\Models\ActivityLog;
use App\Models\Menu;
use App\Models\Setting;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Largest merge in the app — 7 settings groups behind one page: UiSettings
 * ('ui'), NavbarSettings ('navbar'), FooterSettings ('footer'),
 * AnnouncementSettings ('announcement'), SectionsSettings ('sections'),
 * MenuSettings ('menu'), SocialLinkSettings ('social_links' — the real
 * group string, confirmed by reading the source; not 'social_link'). Same
 * $settingsGroups multi-group override pattern as SeoControlCenter /
 * SecurityAccessSettings. UiSettings' 22 hero/spec/footer-pill fields are
 * carried over byte-for-byte — no cleanup, this is the highest
 * content-risk sub-merge in the whole reorg (SettingsFactoryDefaultsTest's
 * ui_settings_factory_defaults_includes_all_22_hero_keys assertion is the
 * regression guard).
 */
class CustomizationSettings extends SettingsPage
{
    protected static ?string $title = 'Customization';

    protected static string $settingsGroup = 'ui';

    /** @var string[] */
    protected static array $settingsGroups = ['ui', 'navbar', 'footer', 'announcement', 'sections', 'menu', 'social_links'];

    protected static ?int $navigationSort = 24;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-rectangle-group';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Customization')
                    ->columnSpanFull()
                    ->activeTab(fn (): int => (int) request()->query('tab', 1))
                    ->tabs([
                        $this->heroUiCopyTab(),
                        $this->navbarCopyTab(),
                        $this->footerBadgesStatsTab(),
                        $this->announcementBarTab(),
                        $this->homepageSectionLimitsTab(),
                        $this->menusSocialTab(),
                    ]),
            ]);
    }

    private function heroUiCopyTab(): Tab
    {
        return Tab::make('Hero & UI Copy')
            ->icon('heroicon-o-paint-brush')
            ->schema([
                Section::make('Hero Section')
                    ->description('Configure the homepage hero banner text and badges.')
                    ->schema([
                        AdminUi::translatableTabs('Hero Text Locales', [
                            'hero_index_badge' => ['label' => 'Index Badge'],
                            'hero_live_status' => ['label' => 'Live Status'],
                            'hero_eyebrow' => ['label' => 'Eyebrow'],
                            'hero_subtext_default' => ['label' => 'Subtext Default', 'type' => 'textarea', 'rows' => 3],
                            'hero_spec_title' => ['label' => 'Spec Title'],
                            'hero_source_label' => ['label' => 'Source Label'],
                            'hero_source_badge' => ['label' => 'Source Badge'],
                            'hero_search_strip' => ['label' => 'Search Strip'],
                            'hero_search_meta_hint' => ['label' => 'Search Meta Hint'],
                            'hero_indexed_label' => ['label' => 'Indexed Label'],
                        ]),
                    ]),

                Section::make('Spec Table')
                    ->description('Configure the specification table labels and values.')
                    ->schema([
                        AdminUi::translatableTabs('Spec Table Locales', [
                            'hero_spec_r1_label' => ['label' => 'Row 1 Label'],
                            'hero_spec_r2_label' => ['label' => 'Row 2 Label'],
                            'hero_spec_r2_value' => ['label' => 'Row 2 Value'],
                            'hero_spec_r3_label' => ['label' => 'Row 3 Label'],
                            'hero_spec_r3_value' => ['label' => 'Row 3 Value'],
                            'hero_spec_r4_label' => ['label' => 'Row 4 Label'],
                            'hero_spec_r4_value' => ['label' => 'Row 4 Value', 'helperText' => 'Also shown as the dispatch-time trust badge on the search zero-results page — kept in sync automatically, no separate field to update.'],
                            'hero_spec_r5_label' => ['label' => 'Row 5 Label'],
                            'hero_spec_r5_value' => ['label' => 'Row 5 Value'],
                        ]),
                    ]),

                Section::make('Footer Pills')
                    ->description('Configure the three footer pill badges beneath the hero.')
                    ->schema([
                        AdminUi::translatableTabs('Footer Pills Locales', [
                            'hero_footer_pill_1' => ['label' => 'Pill 1'],
                            'hero_footer_pill_2' => ['label' => 'Pill 2'],
                            'hero_footer_pill_3' => ['label' => 'Pill 3', 'helperText' => 'If this states a countries count, keep the number in sync with Appearance → Stats Counter → Countries Count (also shown in the footer and search console page).'],
                        ]),
                    ]),
            ]);
    }

    private function navbarCopyTab(): Tab
    {
        return Tab::make('Navbar Copy')
            ->icon('heroicon-o-bars-3-bottom-left')
            ->schema([
                Section::make('Navigation Labels')
                    ->description('Text shown in the storefront navbar. Leave a field empty to use the built-in default.')
                    ->schema([
                        Forms\Components\TextInput::make('account_label')
                            ->label('Account Menu Label')
                            ->placeholder('Account')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('my_account_label')
                            ->label('My Account Link')
                            ->placeholder('My Account')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('sign_in_label')
                            ->label('Sign In Link')
                            ->placeholder('Sign In')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('sign_in_register_label')
                            ->label('Sign In / Register Link')
                            ->placeholder('Sign In / Register')
                            ->maxLength(50),
                    ])->columns(2),
                Section::make('Mini-Cart Labels')
                    ->description('Text inside the dropdown mini-cart.')
                    ->schema([
                        Forms\Components\TextInput::make('cart_label')
                            ->label('Cart Label')
                            ->placeholder('Cart')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('cart_title')
                            ->label('Mini-Cart Title')
                            ->placeholder('Your Cart')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('subtotal_label')
                            ->label('Subtotal Label')
                            ->placeholder('Subtotal')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('view_cart_label')
                            ->label('View Cart Button')
                            ->placeholder('View Cart')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('checkout_label')
                            ->label('Checkout Button')
                            ->placeholder('Checkout')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('remove_label')
                            ->label('Remove Item Label')
                            ->placeholder('Remove')
                            ->maxLength(50),
                    ])->columns(2),
            ]);
    }

    private function footerBadgesStatsTab(): Tab
    {
        return Tab::make('Footer Badges & Stats')
            ->icon('heroicon-o-rectangle-group')
            ->schema([
                Section::make('Trust Badges')
                    ->description('The four trust badges in the storefront footer. Leave a field empty to use the built-in default.')
                    ->schema([
                        Forms\Components\TextInput::make('oem_badge_text')->label('OEM Badge — Title')->placeholder('Genuine OEM')->maxLength(60),
                        Forms\Components\TextInput::make('oem_badge_subtext')->label('OEM Badge — Subtitle')->maxLength(100),
                        Forms\Components\TextInput::make('shipping_badge_text')->label('Shipping Badge — Title')->maxLength(60),
                        Forms\Components\TextInput::make('shipping_badge_subtext')->label('Shipping Badge — Subtitle')->maxLength(100),
                        Forms\Components\TextInput::make('returns_badge_text')->label('Returns Badge — Title')->maxLength(60),
                        Forms\Components\TextInput::make('returns_badge_subtext')->label('Returns Badge — Subtitle')->maxLength(100),
                        Forms\Components\TextInput::make('security_badge_text')->label('Security Badge — Title')->maxLength(60),
                        Forms\Components\TextInput::make('security_badge_subtext')->label('Security Badge — Subtitle')->maxLength(100),
                    ])->columns(2),
                Section::make('Footer Stats & Payments')
                    ->description('The stats strip and accepted-payment labels in the footer.')
                    ->schema([
                        Forms\Components\TextInput::make('stat_parts_label')
                            ->label('Stat — OEM Numbers Label')
                            ->placeholder('OEM Numbers')
                            ->helperText('Leave empty to use the built-in translated default ("OEM Numbers" / "OEM-Nummern" / …).')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('stat_parts')
                            ->label('Stat — OEM Numbers Value')
                            ->placeholder('e.g. 1.2M+')
                            ->helperText('Should match Appearance → Stats Counter → Parts Count (the same "OEM numbers sourced" figure shown on the homepage stats section).')
                            ->maxLength(60),
                        Forms\Components\TextInput::make('stat_countries_label')
                            ->label('Stat — Countries Label')
                            ->placeholder('Countries')
                            ->helperText('Leave empty to use the built-in translated default.')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('stat_countries')
                            ->label('Stat — Countries Value')
                            ->placeholder('e.g. 27')
                            ->helperText('Should match Appearance → Stats Counter → Countries Count — shown identically on the homepage hero pill and the search console page.')
                            ->maxLength(60),
                        Forms\Components\TextInput::make('stat_languages_label')
                            ->label('Stat — Languages Label')
                            ->placeholder('Languages')
                            ->helperText('Leave empty to use the built-in translated default.')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('stat_languages')
                            ->label('Stat — Languages Value')
                            ->placeholder('e.g. 5 languages')
                            ->maxLength(60),
                        Forms\Components\TagsInput::make('payment_methods')
                            ->label('Accepted Payment Labels')
                            ->placeholder('Add a label…')
                            ->helperText('Shown as badges in the footer (e.g. VISA, MASTERCARD, SEPA). Leave empty for the defaults.')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    private function announcementBarTab(): Tab
    {
        return Tab::make('Announcement Bar')
            ->icon('heroicon-o-megaphone')
            ->schema([
                Section::make('Announcement Configuration')
                    ->description('Set up a top banner to promote sales, updates, or maintenance windows.')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Announcement Bar')
                            ->helperText('Show this marquee banner at the top of the storefront pages')
                            ->default(false),

                        Forms\Components\Toggle::make('dismissable')
                            ->label('Allow Users to Dismiss')
                            ->helperText('Allow visitors to close the announcement bar during their session')
                            ->default(true),

                        AdminUi::translatableTabs('Announcement Text', [
                            'text' => [
                                'label' => 'Text',
                                'type' => 'textarea',
                                'rows' => 2,
                                'helperText' => '',
                                'placeholders' => [
                                    'en' => 'e.g. Special offer: Free shipping on orders over €150!',
                                    'de' => 'e.g. Sonderangebot: Kostenloser Versand ab 150 €!',
                                    'lt' => 'e.g. Specialus pasiūlymas: Nemokamas pristatymas nuo 150 €!',
                                    'fr' => 'e.g. Offre spéciale : Livraison gratuite à partir de 150 € !',
                                    'es' => 'e.g. ¡Oferta especial: Envío gratis en pedidos superiores a 150 €!',
                                ],
                            ],
                        ]),

                        AdminUi::translatableTabs('CTA Button Text', [
                            'cta_text' => [
                                'label' => 'CTA',
                                'helperText' => '',
                                'placeholders' => [
                                    'en' => 'e.g. Shop Now',
                                    'de' => 'e.g. Jetzt kaufen',
                                    'lt' => 'e.g. Pirkite dabar',
                                    'fr' => 'e.g. Acheter maintenant',
                                    'es' => 'e.g. Comprar ahora',
                                ],
                            ],
                        ])->helperText('Optional call-to-action button text displayed on the announcement bar.'),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Background Color')
                            ->helperText('Background color of the banner')
                            ->default('#F59E0B'),

                        Forms\Components\ColorPicker::make('text_color')
                            ->label('Text Color')
                            ->helperText('Text color of the banner')
                            ->default('#1E293B'),

                        Forms\Components\TextInput::make('url')
                            ->label('Link URL (Optional)')
                            ->url()
                            ->maxLength(500)
                            ->helperText('Makes the entire announcement bar clickable to redirect users')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    private function homepageSectionLimitsTab(): Tab
    {
        return Tab::make('Homepage Section Limits')
            ->icon('heroicon-o-squares-2x2')
            ->schema([
                Section::make('Content Limits')
                    ->description('Control how many items are displayed in each homepage section type.')
                    ->schema([
                        Forms\Components\TextInput::make('testimonials_limit')
                            ->label('Testimonials Section Limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(6),

                        Forms\Components\TextInput::make('faq_limit')
                            ->label('FAQ Section Limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(10),

                        Forms\Components\TextInput::make('blog_limit')
                            ->label('Blog Section Limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(3),

                        Forms\Components\TextInput::make('manufacturers_limit')
                            ->label('Manufacturers Section Limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(12),
                    ])->columns(2),
            ]);
    }

    private function menusSocialTab(): Tab
    {
        $menus = Menu::orderBy('name')->get();

        return Tab::make('Menus & Social')
            ->icon('heroicon-o-bars-3')
            ->schema([
                Section::make('Navigation Menus')
                    ->description('Configure which menus appear in the storefront header and footer. Menu items are managed via the Content > Menus resource.')
                    ->schema([
                        Forms\Components\Repeater::make('menus')
                            ->label('Active Menus')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Menu Name')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('location')
                                    ->label('Location')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('items_count')
                                    ->label('Items')
                                    ->disabled()
                                    ->dehydrated(false),
                            ])
                            ->default(collect($menus)->mapWithKeys(fn ($menu) => [
                                $menu->id => [
                                    'name' => $menu->name,
                                    'location' => $menu->location ?? 'Not set',
                                    'items_count' => $menu->items()->count() . ' items',
                                ],
                            ])->toArray())
                            ->columns(3)
                            ->disabled(),
                    ]),

                Section::make('Footer Links')
                    ->description('Configure footer navigation link behavior.')
                    ->schema([
                        Forms\Components\Toggle::make('footer_show_about')
                            ->label('Show "About Us" in Footer')
                            ->default(true),
                        Forms\Components\Toggle::make('footer_show_contact')
                            ->label('Show "Contact" in Footer')
                            ->default(true),
                        Forms\Components\Toggle::make('footer_show_faq')
                            ->label('Show "FAQ" in Footer')
                            ->default(true),
                        Forms\Components\Toggle::make('footer_show_blog')
                            ->label('Show "Blog" in Footer')
                            ->default(true),
                    ])->columns(2),

                Section::make('Social Media Profiles')
                    ->description('Link your social media profiles. These appear in the storefront footer and social sharing metadata.')
                    ->schema([
                        Forms\Components\TextInput::make('facebook_url')
                            ->label('Facebook URL')
                            ->helperText('Canonical social link for footer display. Contact Settings has a quick-link shortcut to the same URL.')
                            ->url()
                            ->placeholder('https://facebook.com/yourpage')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('instagram_url')
                            ->label('Instagram URL')
                            ->url()
                            ->placeholder('https://instagram.com/yourpage')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('twitter_url')
                            ->label('X (Twitter) URL')
                            ->url()
                            ->placeholder('https://x.com/yourhandle')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('linkedin_url')
                            ->label('LinkedIn URL')
                            ->helperText('Canonical social link for footer display. Contact Settings has a quick-link shortcut to the same URL.')
                            ->url()
                            ->placeholder('https://linkedin.com/company/yourpage')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('youtube_url')
                            ->label('YouTube URL')
                            ->url()
                            ->placeholder('https://youtube.com/@yourchannel')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tiktok_url')
                            ->label('TikTok URL')
                            ->url()
                            ->placeholder('https://tiktok.com/@yourhandle')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Social Display Settings')
                    ->description('Control which social icons appear in the storefront footer.')
                    ->schema([
                        Forms\Components\Toggle::make('show_in_footer')
                            ->label('Show Social Icons in Footer')
                            ->default(true),
                        Forms\Components\Select::make('footer_icon_style')
                            ->label('Icon Style')
                            ->options([
                                'filled' => 'Filled',
                                'outlined' => 'Outlined',
                            ])
                            ->default('filled'),
                    ])->columns(2),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Multi-group overrides — copied verbatim from SeoControlCenter.php /
    // SecurityAccessSettings.php. See class docblock.
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
                    Log::warning("Failed to decrypt setting {$setting->key}: " . $e->getMessage());
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
            ->body('Cache cleared for: ' . implode(', ', static::$settingsGroups))
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
            if ($key === 'menus') {
                // The Repeater is disabled()->dehydrated(false) at the field
                // level but Livewire still surfaces its default() as a key
                // in $this->data — skip it explicitly so nothing ever tries
                // to write a Setting row named 'menu.menus'.
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (is_array($value)) {
                $value = empty($value) ? '' : json_encode($value);
            }

            $service->set($this->groupForKey($key) . '.' . $key, $value);

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

    private static function castDefinitionValue(mixed $value, string $type): mixed
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
