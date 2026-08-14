<?php

namespace App\Filament\Pages\Settings;

use App\Enums\SettingType;
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
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Rewritten in place (same class/slug, 'appearance-settings' — no redirect
 * shim needed for this page's own URL) to merge PreloaderSettings
 * ('preloader') and StatsCounterSettings ('stats_counter' — the real group
 * string, confirmed by reading the source; not 'stats-counter') into it as
 * 2 additional tabs. Same $settingsGroups multi-group override pattern as
 * SeoControlCenter / SecurityAccessSettings.
 */
class AppearanceSettings extends SettingsPage
{
    protected static ?string $title = 'Appearance';

    protected static string $settingsGroup = 'appearance';

    /** @var string[] */
    protected static array $settingsGroups = ['appearance', 'preloader', 'stats_counter'];

    protected static ?int $navigationSort = 31;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-paint-brush';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Appearance')
                    ->columnSpanFull()
                    ->tabs([
                        $this->colorsCssTab(),
                        $this->preloaderTab(),
                        $this->statsCounterTab(),
                    ]),
            ]);
    }

    private function colorsCssTab(): Tab
    {
        return Tab::make('Colors & CSS')
            ->icon('heroicon-o-swatch')
            ->schema([
                Section::make('Brand Colors')
                    ->description('Choose colors used across storefront headers, primary call-to-actions, and accent borders.')
                    ->schema([
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Primary Brand Color')
                            ->helperText('Main brand identity color (buttons, active tabs, header highlights)')
                            ->default('#0B3A68'),

                        Forms\Components\ColorPicker::make('accent_color')
                            ->label('Accent/Highlight Color')
                            ->helperText('Used for alert bars, important callouts, badges, or rating stars')
                            ->default('#F59E0B'),
                    ])->columns(2),

                Section::make('Custom CSS Injection')
                    ->description('Add bespoke CSS stylesheets to customize the frontend appearance. Injected directly into the document head.')
                    ->schema([
                        Forms\Components\Toggle::make('custom_css_enabled')
                            ->label('Enable Custom CSS Stylesheet')
                            ->helperText('When enabled, the rules below are loaded on all public storefront pages')
                            ->default(false),

                        Forms\Components\Textarea::make('custom_css')
                            ->label('Stylesheet Rules')
                            ->helperText('Write valid CSS. e.g. body { background-color: #fafafa; }')
                            ->rows(8)
                            ->columnSpanFull()
                            ->default(null),
                    ]),
            ]);
    }

    private function preloaderTab(): Tab
    {
        return Tab::make('Preloader')
            ->icon('heroicon-o-arrow-path')
            ->schema([
                Section::make('Preloader Configuration')
                    ->description('Control the full-screen loading animation on storefront pages.')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Preloader')
                            ->helperText('Show a loading screen on page navigation')
                            ->default(false),

                        Forms\Components\Select::make('path_mode')
                            ->label('Path Mode')
                            ->options([
                                'all' => 'All Pages',
                                'include' => 'Include Only',
                                'exclude' => 'Exclude Only',
                            ])
                            ->default('all'),

                        Forms\Components\TagsInput::make('path_patterns')
                            ->label('Path Patterns')
                            ->helperText('URL patterns to match (e.g., /parts/*, /cart)')
                            ->default([]),

                        Forms\Components\TextInput::make('min_display_ms')
                            ->label('Minimum Display Time (ms)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5000)
                            ->default(450),

                        Forms\Components\TextInput::make('max_display_ms')
                            ->label('Maximum Display Time (ms)')
                            ->numeric()
                            ->minValue(500)
                            ->maxValue(60000)
                            ->default(6000),
                    ])->columns(2),

                Section::make('Preloader Text')
                    ->description('Customize the multilang text displayed during the preloader animation.')
                    ->schema([
                        AdminUi::translatableTabs('Preloader Text Locales', [
                            'headline' => ['label' => 'Headline'],
                            'spec_line' => ['label' => 'Spec Line'],
                            'subline' => ['label' => 'Subline'],
                            'status_line' => ['label' => 'Status Line'],
                            'foot_left' => ['label' => 'Footer Left'],
                            'foot_right' => ['label' => 'Footer Right'],
                            'aria_label' => ['label' => 'ARIA Label'],
                        ]),
                    ]),
            ]);
    }

    private function statsCounterTab(): Tab
    {
        return Tab::make('Stats Counter')
            ->icon('heroicon-o-chart-bar-square')
            ->schema([
                Section::make('Homepage Statistics Display')
                    ->description('These numbers are displayed on the public landing page hero grid block to establish trust.')
                    ->schema([
                        Forms\Components\TextInput::make('customers_count')
                            ->label('Total Active Customers')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('2500')
                            ->default(2500),

                        Forms\Components\TextInput::make('parts_count')
                            ->label('Catalog Part Numbers Count')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('1000000')
                            ->default(1000000),

                        Forms\Components\TextInput::make('countries_count')
                            ->label('Countries Served Count')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(250)
                            ->placeholder('27')
                            ->default(27),

                        Forms\Components\TextInput::make('rating')
                            ->label('Store Trust Rating')
                            ->helperText('Shown as a fractional score (e.g. 4.9 / 5.0 rating)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5)
                            ->step(0.1)
                            ->placeholder('4.9')
                            ->default(4.9),

                        Forms\Components\TextInput::make('orders_count')
                            ->label('Processed Orders Count')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('120000')
                            ->default(120000),

                        Forms\Components\Toggle::make('show_section')
                            ->label('Render Stats Section on Homepage')
                            ->helperText('Toggles layout visibility of stats numbers in the public hero section')
                            ->default(true),
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
