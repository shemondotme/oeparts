<?php

namespace App\Filament\Pages\Settings;

use App\Enums\SettingType;
use App\Filament\Resources\PageResource;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Merges GeneralSettings ('general'), CompanySettings ('company'), and
 * StoreSettings ('store') — same $settingsGroups multi-group override
 * pattern as SeoControlCenter / SecurityAccessSettings / SystemMaintenanceSettings.
 * GeneralSettings' "Localization & Branding Defaults" section moves into the
 * Regional Defaults tab alongside StoreSettings' own currency/locale fields
 * (both edit display-formatting concerns, StoreSettings' currency display
 * already reads live from the 'general' group via settings(), so nothing
 * about that cross-group read needs to change).
 */
class GeneralBrandSettings extends SettingsPage
{
    protected static ?string $title = 'General & Brand';

    protected static string $settingsGroup = 'general';

    /** @var string[] */
    protected static array $settingsGroups = ['general', 'company', 'store'];

    protected static ?int $navigationSort = 5;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-identification';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('General & Brand')
                    ->columnSpanFull()
                    ->activeTab(fn (): int => (int) request()->query('tab', 1))
                    ->tabs([
                        $this->siteIdentityTab(),
                        $this->companyLegalTab(),
                        $this->regionalDefaultsTab(),
                        $this->scriptInjectionTab(),
                    ]),
            ]);
    }

    private function siteIdentityTab(): Tab
    {
        return Tab::make('Site Identity')
            ->icon('heroicon-o-identification')
            ->schema([
                Section::make('Site Identity & Branding')
                    ->description('Upload branding assets and configure primary public identity details.')
                    ->schema([
                        FileUpload::make('logo_id')
                            ->label('Site Logo')
                            ->helperText('Used in structured data (Organization JSON-LD) shown to search engines. The storefront navbar itself uses a coded brand mark, not this upload.')
                            ->disk('public')
                            ->directory('branding')
                            ->image()
                            ->maxSize(2048)
                            ->columnSpanFull(),

                        FileUpload::make('favicon_id')
                            ->label('Favicon')
                            ->helperText('Overrides the browser tab icon site-wide when set. Leave empty to keep the coded Industrial Blueprint mark.')
                            ->disk('public')
                            ->directory('branding')
                            ->image()
                            ->maxSize(512)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('site_name')
                            ->label('Site Name')
                            ->maxLength(255)
                            ->required()
                            ->default('OeParts'),

                        Forms\Components\TextInput::make('site_url')
                            ->label('Site URL')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://oeparts.test')
                            ->default(null),

                        Forms\Components\TextInput::make('site_email')
                            ->label('Public Contact Email')
                            ->helperText('Canonical email shown in site header/footer. Contact Settings page has a separate email field for customer support routing.')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('info@oeparts.lt')
                            ->default(null),

                        Forms\Components\TextInput::make('site_phone')
                            ->label('Public Contact Phone')
                            ->helperText('Canonical phone for public display. Contact Settings page has a separate phone for support routing.')
                            ->tel()
                            ->minLength(5)
                            ->maxLength(30)
                            ->placeholder('+370 600 00000')
                            ->default(null),

                        Placeholder::make('registered_address_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content('Your registered company address (printed on invoices) is set on the Company & Legal tab above.'),

                        Placeholder::make('homepage_routing_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Which page loads at the site root is set on that page\'s own edit screen (a "Set as Homepage" toggle) — see <a href="'
                                . PageResource::getUrl()
                                . '" class="fi-link text-primary-600">Pages</a>.'
                            )),
                    ])->columns(2),
            ]);
    }

    private function companyLegalTab(): Tab
    {
        return Tab::make('Company & Legal')
            ->icon('heroicon-o-building-office-2')
            ->schema([
                Section::make('Company Information')
                    ->description('Company details used on invoices, emails, and legal pages.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Company Name')
                            ->maxLength(255)
                            ->required()
                            ->default('OeParts'),

                        Forms\Components\TextInput::make('vat_number')
                            ->label('VAT Number')
                            ->maxLength(50)
                            ->placeholder('LT123456789')
                            ->default(null),

                        Forms\Components\TextInput::make('registration_number')
                            ->label('Registration Number')
                            ->maxLength(50)
                            ->placeholder('123456789')
                            ->default(null),

                        Forms\Components\TextInput::make('managing_director')
                            ->label('Managing Director / Authorised Representative')
                            ->helperText('Name(s) of the person(s) legally authorised to represent the company — required on the Legal Notice page for corporate entities under most EU member states\' commercial disclosure law (e.g. German §5 TMG).')
                            ->maxLength(255)
                            ->placeholder('Jane Doe')
                            ->columnSpanFull()
                            ->default(null),
                    ])->columns(2),

                Section::make('Contact Details')
                    ->description('Contact information for customer-facing communications.')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Company Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('info@oeparts.lt')
                            ->default(null),

                        Forms\Components\TextInput::make('phone')
                            ->label('Company Phone')
                            ->tel()
                            ->maxLength(30)
                            ->placeholder('+370 600 00000')
                            ->default(null),

                        Forms\Components\Textarea::make('address')
                            ->label('Registered Address')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder("Ulonų g. 5, Vilnius, Lithuania")
                            ->columnSpanFull()
                            ->default(null),
                    ])->columns(2),
            ]);
    }

    private function regionalDefaultsTab(): Tab
    {
        return Tab::make('Regional Defaults')
            ->icon('heroicon-o-globe-europe-africa')
            ->schema([
                Section::make('Localization & Branding Defaults')
                    ->description('Set default currencies, languages, timezones, and display formatting rules.')
                    ->schema([
                        Forms\Components\TextInput::make('site_tagline')
                            ->label('Store Tagline')
                            ->helperText('Shown in the storefront footer.')
                            ->maxLength(255)
                            ->default('The central hub for genuine OEM auto parts in Europe.'),

                        Forms\Components\Select::make('default_locale')
                            ->label('Default Frontend Language')
                            ->options([
                                'en' => 'English (EN)',
                                'de' => 'Deutsch (DE)',
                                'lt' => 'Lietuvių (LT)',
                                'fr' => 'Français (FR)',
                                'es' => 'Español (ES)',
                            ])
                            ->required()
                            ->default('en'),

                        Forms\Components\Select::make('timezone')
                            ->label('System Timezone')
                            ->options(collect(\DateTimeZone::listIdentifiers(\DateTimeZone::EUROPE))
                                ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                ->toArray())
                            ->searchable()
                            ->required()
                            ->default('Europe/Vilnius'),

                        Forms\Components\Select::make('date_format')
                            ->label('System Date Format')
                            ->options([
                                'd/m/Y' => 'DD/MM/YYYY (e.g. 14/03/2025)',
                                'Y-m-d' => 'YYYY-MM-DD (e.g. 2025-03-14)',
                                'm/d/Y' => 'MM/DD/YYYY (e.g. 03/14/2025)',
                                'j F Y' => 'D Month YYYY (e.g. 14 March 2025)',
                            ])
                            ->required()
                            ->default('d/m/Y'),

                        Forms\Components\Select::make('currency')
                            ->label('Base Store Currency')
                            ->helperText('This is the single canonical currency setting used everywhere prices are displayed or charged. The Currency Configuration section below shows it read-only for reference.')
                            ->options([
                                'EUR' => 'EUR (€)',
                                'USD' => 'USD ($)',
                                'GBP' => 'GBP (£)',
                                'CHF' => 'CHF (Fr)',
                                'PLN' => 'PLN (zł)',
                                'SEK' => 'SEK (kr)',
                            ])
                            ->required()
                            ->default('EUR'),

                        Forms\Components\TextInput::make('currency_symbol')
                            ->label('Currency Character')
                            ->helperText('Must match the Base Store Currency.')
                            ->maxLength(10)
                            ->placeholder('€')
                            ->required()
                            ->default('€'),
                    ])->columns(2),

                Section::make('Currency Configuration')
                    ->description('Set the base currency for your store and formatting preferences.')
                    ->schema([
                        Placeholder::make('current_currency_display')
                            ->label('Store Currency')
                            ->content(function () {
                                return settings('general.currency', 'EUR') . ' (' . settings('general.currency_symbol', '€') . ')';
                            })
                            ->helperText('Currency is configured above under Localization & Branding Defaults. This is a read-only reference.'),

                        Forms\Components\Select::make('currency_position')
                            ->label('Symbol Position')
                            ->options([
                                'before' => 'Before amount (€100)',
                                'after' => 'After amount (100€)',
                            ])
                            ->default('after'),
                    ])->columns(2),

                Section::make('Locale & Formatting')
                    ->description('Regional formatting rules for prices, dates, and numbers.')
                    ->schema([
                        Forms\Components\Select::make('decimal_separator')
                            ->label('Decimal Separator')
                            ->options([
                                '.' => 'Dot (12.34)',
                                ',' => 'Comma (12,34)',
                            ])
                            ->default('.'),

                        Forms\Components\Select::make('thousand_separator')
                            ->label('Thousand Separator')
                            ->options([
                                ',' => 'Comma (1,234)',
                                '.' => 'Dot (1.234)',
                                ' ' => 'Space (1 234)',
                            ])
                            ->default(','),
                    ])->columns(2),
            ]);
    }

    private function scriptInjectionTab(): Tab
    {
        return Tab::make('Script Injection')
            ->icon('heroicon-o-code-bracket')
            ->schema([
                Section::make('Global Injection Scripts')
                    ->description('Inject trackers or customization script blocks directly into storefront markup.')
                    ->schema([
                        Forms\Components\Textarea::make('header_scripts')
                            ->label('Header Injection Block')
                            ->helperText('Injected inside <head> tags on all public pages')
                            ->rows(4)
                            ->columnSpanFull()
                            ->default(null),

                        Forms\Components\Textarea::make('footer_scripts')
                            ->label('Footer Injection Block')
                            ->helperText('Injected before the ending </body> tag on all public pages')
                            ->rows(4)
                            ->columnSpanFull()
                            ->default(null),
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
