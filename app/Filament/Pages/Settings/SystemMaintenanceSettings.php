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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema as IlluminateSchema;

/**
 * Merges MaintenanceSettings ('maintenance' group, real editable fields)
 * with AboutLicenseSettings ('about') and DatabaseSettings ('database') —
 * both 100% disabled()/dehydrated(false) display-only Placeholders/inputs,
 * confirmed nothing in either group is ever actually saved. Same
 * $settingsGroups multi-group override pattern as SeoControlCenter /
 * SecurityAccessSettings; 'about' and 'database' contribute zero writable
 * keys so they ride along in fillForm()/persistChanges() harmlessly.
 */
class SystemMaintenanceSettings extends SettingsPage
{
    protected static ?string $title = 'System & Maintenance';

    protected static string $settingsGroup = 'maintenance';

    /** @var string[] */
    protected static array $settingsGroups = ['maintenance', 'about', 'database'];

    protected static ?int $navigationSort = 36;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-wrench-screwdriver';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('System & Maintenance')
                    ->columnSpanFull()
                    ->activeTab(fn (): int => (int) request()->query('tab', 1))
                    ->tabs([
                        $this->maintenanceModeTab(),
                        $this->aboutDatabaseTab(),
                    ]),
            ]);
    }

    private function maintenanceModeTab(): Tab
    {
        return Tab::make('Maintenance Mode')
            ->icon('heroicon-o-wrench-screwdriver')
            ->schema([
                Section::make('Maintenance Configuration')
                    ->description('Take the store offline for updates or service migrations. Allowed IPs will bypass the restriction.')
                    ->schema([
                        Forms\Components\Toggle::make('enabled')
                            ->label('Enable Maintenance Mode')
                            ->helperText('Put the storefront offline. Visitors will see the maintenance message.')
                            ->default(false),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Emergency Support Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('admin@oeparts.lt')
                            ->helperText('Shown on the maintenance page for contact inquiries')
                            ->default(null),

                        AdminUi::translatableTabs('Maintenance Message', [
                            'message' => [
                                'label' => 'Message',
                                'type' => 'textarea',
                                'rows' => 2,
                                'placeholders' => [
                                    'en' => "We're performing scheduled maintenance. We'll be back shortly.",
                                    'de' => 'Wir führen planmäßige Wartungsarbeiten durch. Wir sind in Kürze wieder da.',
                                    'lt' => 'Atliekami profilaktiniai darbai. Netrukus grįšime.',
                                    'fr' => 'Nous effectuons une maintenance programmée. Nous serons de retour sous peu.',
                                    'es' => 'Estamos realizando tareas de mantenimiento programadas. Volveremos pronto.',
                                ],
                            ],
                        ]),

                        Forms\Components\Textarea::make('allowed_ips')
                            ->label('Bypass IP Whitelist')
                            ->placeholder("e.g. 192.168.1.1\n80.90.100.110")
                            ->helperText('One IP address per line. These IPs will retain full access to the site.')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('estimated_back_at')
                            ->label('Estimated Return Time')
                            ->placeholder('e.g. 2025-06-02 18:00')
                            ->helperText('Visible text indicating when service is expected to resume')
                            ->maxLength(50)
                            ->default(null),

                        Forms\Components\Toggle::make('show_estimated_time')
                            ->label('Show Countdown/Time')
                            ->helperText('Display the estimated return time to store visitors')
                            ->default(false),

                        Forms\Components\TextInput::make('retry_after')
                            ->label('HTTP Retry-After Header (Seconds)')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('3600')
                            ->helperText('Tells search crawlers when to retry indexing (default 3600s / 1 hour)')
                            ->default(3600),
                    ])->columns(2),
            ]);
    }

    private function aboutDatabaseTab(): Tab
    {
        $tables = [];
        if (IlluminateSchema::hasTable('settings')) {
            try {
                // SHOW TABLE STATUS is MySQL-specific syntax (the mandated
                // production driver per CLAUDE.md); guarded so any other
                // driver degrades to an empty table list instead of a fatal
                // query error.
                $tables = DB::select('SHOW TABLE STATUS');
            } catch (\Throwable $e) {
                $tables = [];
            }
        }

        return Tab::make('About & Database')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Platform Information')
                    ->description('Display-only information about the OeParts platform installation.')
                    ->schema([
                        Forms\Components\TextInput::make('app_version')
                            ->label('Application Version')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(config('app.version', '1.0.0')),

                        Forms\Components\TextInput::make('laravel_version')
                            ->label('Laravel Version')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(app()->version()),

                        Forms\Components\TextInput::make('php_version')
                            ->label('PHP Version')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(PHP_VERSION),

                        Forms\Components\TextInput::make('mysql_version')
                            ->label('MySQL Version')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(static::databaseVersion()),
                    ])->columns(2),

                Section::make('License')
                    ->description('MIT License — open-source software.')
                    ->schema([
                        Forms\Components\Textarea::make('license_text')
                            ->label('License')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(8)
                            ->columnSpanFull()
                            ->default('MIT License

Copyright (c) ' . date('Y') . ' OeParts

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.'),
                    ]),

                Section::make('Connection Status')
                    ->description('Current database connection information.')
                    ->schema([
                        Forms\Components\TextInput::make('connection')
                            ->label('Driver')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(config('database.default')),

                        Forms\Components\TextInput::make('database_name')
                            ->label('Database Name')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(config('database.connections.' . config('database.default') . '.database')),

                        Forms\Components\TextInput::make('host')
                            ->label('Host')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(config('database.connections.' . config('database.default') . '.host')),
                    ])->columns(3),

                Section::make('Table Summary')
                    ->description('Overview of database tables and their row counts.')
                    ->schema([
                        Forms\Components\Placeholder::make('table_count')
                            ->label('Total Tables')
                            ->content(fn () => count($tables) . ' tables'),

                        Forms\Components\Placeholder::make('total_rows')
                            ->label('Total Rows')
                            ->content(fn () => number_format(collect($tables)->sum('Rows'))),

                        Forms\Components\Placeholder::make('total_size')
                            ->label('Total Size')
                            ->content(fn () => round(collect($tables)->sum('Data_length') / 1024 / 1024, 2) . ' MB'),
                    ])->columns(3),

                Section::make('Actions')
                    ->schema([
                        Forms\Components\Placeholder::make('optimize_hint')
                            ->label('Maintenance')
                            ->content('Run `php artisan db:optimize` from the CLI to optimize tables. Use Backup Dashboard for exports.'),
                    ]),
            ]);
    }

    /**
     * The prior implementation ran the literal SQL `SELECT VERSION()`, which
     * is MySQL-specific syntax — it throws a QueryException (not a graceful
     * null) under any other PDO driver, including the test suite's sqlite
     * connection. PDO::ATTR_SERVER_VERSION is portable across drivers and
     * returns the same value MySQL's VERSION() would in production.
     */
    private static function databaseVersion(): string
    {
        try {
            return DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION) ?? 'Unknown';
        } catch (\Throwable $e) {
            return 'Unknown';
        }
    }

    protected function afterSave(): void
    {
        $previousEnabled = settings('maintenance.enabled', false) === 'true';

        $newEnabled = ($this->data['enabled'] ?? false) === true
            || ($this->data['enabled'] ?? 'false') === 'true';

        if ($previousEnabled !== $newEnabled) {
            if ($newEnabled) {
                Artisan::call('down', [
                    '--retry' => $this->data['retry_after'] ?? 3600,
                    '--allow' => array_filter(explode("\n", str_replace("\r\n", "\n", $this->data['allowed_ips'] ?? ''))),
                ]);
            } else {
                Artisan::call('up');
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Multi-group overrides — copied verbatim from SeoControlCenter.php /
    // SecurityAccessSettings.php, the existing precedent for a SettingsPage
    // spanning >1 settings group. See class docblock.
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
