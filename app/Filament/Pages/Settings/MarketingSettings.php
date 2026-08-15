<?php

namespace App\Filament\Pages\Settings;

use App\Enums\SettingType;
use App\Filament\Resources\CouponResource;
use App\Filament\Support\AdminUi;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Merges IntegrationsSettings ('integrations', relocated here from its old
 * "System & Security" miscategorization), NewsletterSettings ('newsletter'),
 * and the Rush Processing Upsell fields extracted from Checkout
 * ('rush_upsell', new group — see the 2026_08_15_000003 migration that
 * moves any already-seeded checkout.urgent_processing_* rows). Same
 * $settingsGroups multi-group override pattern as SeoControlCenter /
 * SecurityAccessSettings.
 */
class MarketingSettings extends SettingsPage
{
    protected static ?string $title = 'Marketing';

    protected static string $settingsGroup = 'integrations';

    /** @var string[] */
    protected static array $settingsGroups = ['integrations', 'newsletter', 'rush_upsell'];

    protected static ?int $navigationSort = 22;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-megaphone';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Marketing')
                    ->columnSpanFull()
                    ->tabs([
                        $this->integrationsTab(),
                        $this->newsletterTab(),
                        $this->rushProcessingUpsellTab(),
                    ]),
            ]);
    }

    private function integrationsTab(): Tab
    {
        return Tab::make('Integrations')
            ->icon('heroicon-o-puzzle-piece')
            ->schema([
                Section::make('Google Tag Manager')
                    ->description('Injects the standard GTM container snippet into every storefront page (head script + body noscript iframe).')
                    ->schema([
                        Forms\Components\TextInput::make('gtm_id')
                            ->label('Google Tag Manager Container ID')
                            ->placeholder('GTM-XXXXXXX')
                            ->helperText('Container ID format: GTM-XXXXXXX')
                            ->maxLength(50)
                            ->default(null),

                        Forms\Components\Placeholder::make('gsc_verification_note')
                            ->label('')
                            ->content(new HtmlString(
                                'Search Console verification is set on the <a href="'
                                . SeoControlCenter::getUrl()
                                . '" class="fi-link text-primary-600">SEO &amp; Meta</a> page, alongside the other webmaster verification codes.'
                            )),
                    ])->columns(2),

                Section::make('Google Analytics 4 (GA4)')
                    ->description('Expose storefront ecommerce tracking data using standard GA4 streams.')
                    ->schema([
                        Forms\Components\TextInput::make('ga4_measurement_id')
                            ->label('GA4 Stream Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText('E.g. G-H2KL987YZ6')
                            ->maxLength(50)
                            ->default(null),
                    ]),

                Section::make('Marketing Pixels & Customer Service')
                    ->description('Set Facebook tracking ids and load support chat widgets.')
                    ->schema([
                        Forms\Components\TextInput::make('fb_pixel_id')
                            ->label('Facebook Pixel ID')
                            ->placeholder('123456789012345')
                            ->maxLength(50)
                            ->default(null),

                        Forms\Components\TextInput::make('crisp_website_id')
                            ->label('Crisp Website ID')
                            ->placeholder('e.g. 5d57b543-9876-4321-a000-a00000000000')
                            ->maxLength(50)
                            ->default(null),
                    ])->columns(2),
            ]);
    }

    private function newsletterTab(): Tab
    {
        return Tab::make('Newsletter')
            ->icon('heroicon-o-envelope')
            ->schema([
                Section::make('Newsletter Configuration')
                    ->description('Control subscription behavior and rate limiting.')
                    ->schema([
                        Forms\Components\TextInput::make('rate_limit_per_hour')
                            ->label('Max Subscriptions Per IP Per Hour')
                            ->numeric()->minValue(1)->maxValue(100)->default(10),

                        Forms\Components\TextInput::make('rate_window_seconds')
                            ->label('Rate Window (seconds)')
                            ->numeric()->minValue(60)->maxValue(3600)->default(3600),

                        Forms\Components\Toggle::make('double_opt_in')
                            ->label('Double Opt-In')
                            ->helperText('Require email confirmation before subscribing')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    private function rushProcessingUpsellTab(): Tab
    {
        return Tab::make('Rush Processing Upsell')
            ->icon('heroicon-o-bolt')
            ->schema([
                Section::make('Rush Processing Upsell')
                    ->description('Customer-facing paid fast-track option offered at checkout, alongside shipping method selection.')
                    ->schema([
                        Forms\Components\Toggle::make('urgent_processing_enabled')
                            ->label('Offer Rush Processing at Checkout')
                            ->helperText('When on, customers can pay an extra fee to have their order flagged Urgent (same-day dispatch priority) — the same flag operators already set manually from the order view.')
                            ->live()
                            ->default(false),

                        Forms\Components\TextInput::make('urgent_processing_fee')
                            ->label('Rush Processing Fee')
                            ->numeric()->prefix('€')->minValue(0)->step(0.01)->required()
                            ->visible(fn (Get $get) => $get('urgent_processing_enabled'))
                            ->default(9.99),
                    ])->columns(2),

                Section::make('Rush Processing — Customer-Facing Copy')
                    ->description('Shown at checkout in the customer\'s own language. Leave a locale blank to fall back to English.')
                    ->visible(fn (Get $get) => $get('urgent_processing_enabled'))
                    ->schema([
                        AdminUi::translatableTabs('Rush Processing Copy', [
                            'urgent_processing_label' => [
                                'label' => 'Checkout Option Label',
                                'required' => true,
                                'maxLength' => 100,
                                'placeholder' => 'Rush processing',
                            ],
                            'urgent_processing_description' => [
                                'label' => 'Checkout Option Description',
                                'type' => 'textarea',
                                'rows' => 2,
                                'maxLength' => 300,
                                'placeholder' => 'Priority same-day dispatch for orders placed before 2pm on a business day.',
                            ],
                        ]),
                    ]),

                Section::make('Other Promotional Levers')
                    ->schema([
                        Forms\Components\Placeholder::make('coupons_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Discount codes and promo campaigns are managed on the <a href="'
                                . CouponResource::getUrl()
                                . '" class="fi-link text-primary-600">Coupons</a> page.'
                            )),

                        Forms\Components\Placeholder::make('rush_upsell_origin_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content('This was previously part of Store Operations → Checkout & Payments — moved here since it\'s an upsell lever, not a checkout mechanic.'),
                    ]),
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
