<?php

namespace App\Filament\Pages\Settings;

use App\Enums\SettingType;
use App\Models\ActivityLog;
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
 * Merges SearchSettings ('search') and PdpSettings ('pdp') — same
 * $settingsGroups multi-group override pattern as SeoControlCenter /
 * SecurityAccessSettings.
 */
class SearchCatalogSettings extends SettingsPage
{
    protected static ?string $title = 'Search & Catalog';

    protected static string $settingsGroup = 'search';

    /** @var string[] */
    protected static array $settingsGroups = ['search', 'pdp'];

    protected static ?int $navigationSort = 20;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-magnifying-glass';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Search & Catalog')
                    ->columnSpanFull()
                    ->activeTab(fn (): int => (int) request()->query('tab', 1))
                    ->tabs([
                        $this->internalSearchTab(),
                        $this->productPageSectionsTab(),
                    ]),
            ]);
    }

    private function internalSearchTab(): Tab
    {
        return Tab::make('Internal Search')
            ->icon('heroicon-o-magnifying-glass')
            ->schema([
                Section::make('Search Engine Behavior')
                    ->description('Tune criteria controls for normalized query operations and storefront thresholds.')
                    ->schema([
                        Forms\Components\TextInput::make('min_chars')
                            ->label('Minimum Query Characters')
                            ->numeric()->minValue(1)->maxValue(10)->required()
                            ->helperText('Characters required before search results list starts loading')
                            ->default(3),

                        Forms\Components\TextInput::make('autocomplete_count')
                            ->label('Max Autocomplete Dropdown Options')
                            ->numeric()->minValue(1)->maxValue(20)->required()
                            ->helperText('Number of instant matched choices shown in search bar dropdown')
                            ->default(5),

                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label('Rate Limit (Queries per Minute)')
                            ->numeric()->minValue(1)->maxValue(1000)->required()
                            ->helperText('Maximum search requests per client session to block bots')
                            ->default(30),

                        Forms\Components\TextInput::make('max_results')
                            ->label('Max Results per Search Page')
                            ->numeric()->minValue(1)->maxValue(500)->required()
                            ->helperText('Hard cutoff for total matched items returned in standard listing page grids')
                            ->default(50),
                    ])->columns(2),

                Section::make('Search & Diagnostic Logging')
                    ->description('Log operations to optimize query indexes and identify missing OEM part demands. Retention for these logs is set on Security & Access, alongside login and admin-activity audit history.')
                    ->schema([
                        Forms\Components\Toggle::make('log_searches')
                            ->label('Log All Keyword Search Queries')
                            ->helperText('Saves user inputs to track popular searches and catalog items')
                            ->default(true),

                        Forms\Components\Toggle::make('log_failed')
                            ->label('Log Failed Searches')
                            ->helperText('Saves queries yielding zero matches (extremely useful to expand supplier catalogs)')
                            ->default(true),
                    ])->columns(2),

                Section::make('Search Matching Options')
                    ->description('Enable partial string matching and cross-reference code alignments.')
                    ->schema([
                        Forms\Components\Toggle::make('cross_ref_enabled')
                            ->label('Enable Cross-Reference Identifiers')
                            ->helperText('Matches equivalent replacement codes when exact OEM matches are missing')
                            ->default(true),

                        Forms\Components\Toggle::make('partial_match_enabled')
                            ->label('Enable Partial Code Matching')
                            ->helperText('Allows matching partial code segments if full numbers are not matched')
                            ->default(true),

                        Forms\Components\TextInput::make('partial_match_min_length')
                            ->label('Partial Match Code Min Length')
                            ->numeric()->minValue(3)->maxValue(20)
                            ->helperText('Minimum character length of user input before running partial queries')
                            ->default(4),
                    ])->columns(2),

                Section::make('Storefront Search & Popular Results')
                    ->description('Frontend autocomplete API, results pagination, and popular-search caching.')
                    ->schema([
                        Forms\Components\TagsInput::make('supported_languages')
                            ->label('Supported Autocomplete Languages')
                            ->helperText('Locale codes the autocomplete API accepts for the q parameter')
                            ->default(['en', 'de', 'lt', 'fr', 'es']),

                        Forms\Components\TextInput::make('results_limit')
                            ->label('Default Results Limit')
                            ->numeric()->minValue(1)->maxValue(500)->required()
                            ->helperText('Default number of search results returned when not paginating')
                            ->default(50),

                        Forms\Components\TextInput::make('per_page')
                            ->label('Results per Page')
                            ->numeric()->minValue(1)->maxValue(200)->required()
                            ->helperText('Items per page on the paginated search results page')
                            ->default(20),

                        Forms\Components\TextInput::make('popular_days_window')
                            ->label('Popular Searches Window (Days)')
                            ->numeric()->minValue(1)->maxValue(90)->required()
                            ->helperText('How many days back to look when computing popular searches/products')
                            ->default(30),

                        Forms\Components\TextInput::make('popular_limit')
                            ->label('Popular Results Limit')
                            ->numeric()->minValue(1)->maxValue(50)->required()
                            ->helperText('Maximum number of popular search/product entries to show')
                            ->default(8),

                        Forms\Components\TextInput::make('cache_ttl_hours')
                            ->label('Popular Results Cache TTL (Hours)')
                            ->numeric()->minValue(1)->maxValue(168)->required()
                            ->helperText('How long popular-search results are cached before recomputing')
                            ->default(6),
                    ])->columns(2),
            ]);
    }

    private function productPageSectionsTab(): Tab
    {
        return Tab::make('Product Page Sections')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Product Page Sections')
                    ->description('Toggle individual content blocks on the storefront product detail page. Each toggle takes effect immediately (subject to the same 5-minute settings cache as every other settings group).')
                    ->schema([
                        Forms\Components\Toggle::make('show_specifications')
                            ->label('Specifications Table')
                            ->helperText('Key/value spec table, shown only when a product actually has specifications entered')
                            ->default(true),

                        Forms\Components\Toggle::make('show_warranty')
                            ->label('Warranty Block')
                            ->helperText('Shown only when a product has a warranty period set')
                            ->default(true),

                        Forms\Components\Toggle::make('show_video')
                            ->label('Product Video')
                            ->helperText('Shown only when a product has a video URL set')
                            ->default(true),

                        Forms\Components\Toggle::make('show_reviews')
                            ->label('Customer Reviews')
                            ->helperText('Public submission form plus approved-review display; new reviews still require admin approval regardless of this toggle')
                            ->default(true),

                        Forms\Components\Toggle::make('show_related_products')
                            ->label('Related Products')
                            ->helperText('Automatic — same manufacturer or shared vehicle fitment')
                            ->default(true),

                        Forms\Components\Toggle::make('sticky_add_to_cart')
                            ->label('Sticky Mobile Add-to-Cart Bar')
                            ->helperText('Keeps quantity + Add to Cart (and Buy Now, if enabled) visible while scrolling on mobile')
                            ->default(true),

                        Forms\Components\Toggle::make('buy_now_enabled')
                            ->label('"Buy Now" One-Click Checkout Button')
                            ->helperText('Skips the cart page — takes the customer straight to checkout with just this item')
                            ->default(false),

                        Forms\Components\TextInput::make('review_rate_limit_per_hour')
                            ->label('Review Submission Rate Limit (per Hour, per IP)')
                            ->numeric()->minValue(1)->maxValue(100)->required()
                            ->helperText('Guards the public review form against spam submissions')
                            ->default(5),
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
