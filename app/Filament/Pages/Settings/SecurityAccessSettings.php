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
use Illuminate\Support\HtmlString;

/**
 * Merges the old AuthSettings + SecuritySettings pages — their names were
 * near-synonymous (both "Auth & Security"-flavored) and OTP-related fields
 * were split across them (master switch on Security, parameters on Auth)
 * with no way to guess which page owned a given field. Same multi-group
 * override pattern as SeoControlCenter (the only prior precedent): every
 * SettingsPage method that assumes a single static::$settingsGroup is
 * overridden here to loop static::$settingsGroups instead. Field names
 * must stay globally unique across both managed groups — confirmed no
 * overlap between 'auth' and 'security' keys.
 */
class SecurityAccessSettings extends SettingsPage
{
    protected static ?string $title = 'Security & Access';

    protected static string $settingsGroup = 'auth';

    /** @var string[] */
    protected static array $settingsGroups = ['auth', 'security'];

    protected static ?int $navigationSort = 13;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-lock-closed';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Security & Access')
                    ->columnSpanFull()
                    ->tabs([
                        $this->authenticationTab(),
                        $this->firewallSessionsTab(),
                    ]),
            ]);
    }

    private function authenticationTab(): Tab
    {
        return Tab::make('Authentication')
            ->icon('heroicon-o-key')
            ->schema([
                Section::make('One-Time Password (OTP) Auth')
                    ->description('Manage security variables for passwordless guest and B2B user signins.')
                    ->schema([
                        Forms\Components\TextInput::make('otp_length')
                            ->label('OTP Code Length')
                            ->numeric()->minValue(4)->maxValue(8)->required()
                            ->helperText('Number of digits generated for login verification codes')
                            ->default(6),
                        Forms\Components\TextInput::make('otp_expiry_minutes')
                            ->label('OTP Code Lifetime (Minutes)')
                            ->numeric()->minValue(1)->maxValue(60)->required()
                            ->helperText('Minutes before a generated OTP code expires')
                            ->default(10),
                        Forms\Components\TextInput::make('otp_max_attempts')
                            ->label('Maximum Login Attempts')
                            ->numeric()->minValue(1)->maxValue(20)->required()
                            ->helperText('Allowed OTP entries before locking the email session')
                            ->default(3),
                        Forms\Components\TextInput::make('otp_resend_cooldown')
                            ->label('Resend Cooldown (Seconds)')
                            ->numeric()->minValue(10)->maxValue(600)->required()
                            ->helperText('Cooldown delay required before requesting another OTP')
                            ->default(60),
                    ])->columns(2),

                Section::make('Minimum Password Security')
                    ->description('Set password complexity length constraints for account registration.')
                    ->schema([
                        Forms\Components\TextInput::make('customer_password_min')
                            ->label('Customer Password Min Length')
                            ->numeric()->minValue(6)->maxValue(64)->required()
                            ->helperText('Minimum character length for customer portal passwords')
                            ->default(8),
                        Forms\Components\TextInput::make('admin_password_min')
                            ->label('Admin Panel Password Min Length')
                            ->numeric()->minValue(8)->maxValue(64)->required()
                            ->helperText('Minimum character length for administrative user accounts')
                            ->default(12),
                    ])->columns(2),

                Section::make('Registration & Account Policy')
                    ->description('Enable/disable registration endpoints and control session durations.')
                    ->schema([
                        Forms\Components\TextInput::make('customer_session_lifetime')
                            ->label('Portal Session Lifetime (Minutes)')
                            ->numeric()->minValue(1)->maxValue(1440)->required()
                            ->helperText('Minutes of inactivity before logging out a customer session')
                            ->default(120),
                        Forms\Components\Toggle::make('guest_checkout_enabled')
                            ->label('Allow Guest Checkout')
                            ->helperText('Allow users to place orders without creating a formal portal account')
                            ->default(true),
                        Forms\Components\Toggle::make('registration_enabled')
                            ->label('Enable Customer Registration')
                            ->helperText('Expose customer signup and registration page forms on storefront')
                            ->default(true),
                    ])->columns(2),

                Section::make('Social Login (OAuth)')
                    ->description('Google/Facebook app credentials for customer social sign-in. Secrets are encrypted at rest — never stored in .env.')
                    ->schema([
                        Forms\Components\TextInput::make('google_client_id')
                            ->label('Google Client ID')
                            ->placeholder('e.g. xxxxxxxxxxxx.apps.googleusercontent.com')
                            ->maxLength(255)->default(null),
                        Forms\Components\TextInput::make('google_client_secret')
                            ->label('Google Client Secret')
                            ->password()->revealable()
                            ->helperText('Saved encrypted in database')
                            ->default(null),
                        Forms\Components\TextInput::make('facebook_client_id')
                            ->label('Facebook App ID')
                            ->maxLength(255)->default(null),
                        Forms\Components\TextInput::make('facebook_client_secret')
                            ->label('Facebook App Secret')
                            ->password()->revealable()
                            ->helperText('Saved encrypted in database')
                            ->default(null),
                    ])->columns(2),
            ]);
    }

    private function firewallSessionsTab(): Tab
    {
        return Tab::make('Firewall & Sessions')
            ->icon('heroicon-o-shield-check')
            ->schema([
                Section::make('OTP / Two-Step Verification')
                    ->description('Master switch for every storefront OTP step. This is the single control for pausing verification during testing/staging.')
                    ->schema([
                        Forms\Components\Toggle::make('otp_enabled')
                            ->label('Enable OTP / Two-Step Verification (Storefront)')
                            ->helperText('When OFF, every storefront OTP step is skipped entirely — new accounts and guest checkouts are auto-verified with no code sent. Use this to unblock testing when no test SMTP is available. Always re-enable before going live.')
                            ->default(true),
                    ]),

                Section::make('Rate Limiting & Protection')
                    ->description('Set throttle parameters to prevent password brute-forcing and form submission spams.')
                    ->schema([
                        Forms\Components\TextInput::make('login_max_attempts')
                            ->label('Max Login Attempts')
                            ->numeric()->minValue(1)->maxValue(50)->required()
                            ->helperText('Allowed failed signin attempts before IP is throttled/locked')
                            ->default(5),
                        Forms\Components\TextInput::make('login_window_minutes')
                            ->label('Login Attempt Time Window (Minutes)')
                            ->numeric()->minValue(1)->maxValue(60)->required()
                            ->helperText('Failed attempt tracking session block lifetime')
                            ->default(15),
                        Forms\Components\TextInput::make('inquiry_max_per_email')
                            ->label('Max Daily Custom Quotes per Email')
                            ->numeric()->minValue(1)->maxValue(100)->required()
                            ->helperText('Max custom part requests allowed per email block daily')
                            ->default(10),
                    ])->columns(2),

                Section::make('IP Restrictions & Access Control')
                    ->description('Automated-attack IP/CIDR banning and role/permission grants are managed as data, not settings. Honeypot spam protection, CSRF token verification, and production HTTPS enforcement are always active — core application behavior, not optional.')
                    ->schema([
                        Forms\Components\Placeholder::make('ip_blocklist_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Banned IPs and CIDR ranges are managed on the <a href="'
                                . \App\Filament\Resources\IpBlocklistResource::getUrl()
                                . '" class="fi-link text-primary-600">IP Blocklist</a> page — each entry is checked on every request with no restart or cache-clear needed.'
                            )),
                        Forms\Components\Placeholder::make('permission_matrix_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Which role can do what is managed on the <a href="'
                                . \App\Filament\Pages\System\PermissionMatrix::getUrl()
                                . '" class="fi-link text-primary-600">Permission Matrix</a> page.'
                            )),
                        Forms\Components\Placeholder::make('roles_admins_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Admin accounts and named roles are managed on the <a href="'
                                . \App\Filament\Resources\AdminResource::getUrl()
                                . '" class="fi-link text-primary-600">Admins</a> and <a href="'
                                . \App\Filament\Resources\RoleResource::getUrl()
                                . '" class="fi-link text-primary-600">Roles</a> pages.'
                            )),
                    ]),

                Section::make('Session')
                    ->description('Control admin panel session expiry for security compliance.')
                    ->schema([
                        Forms\Components\TextInput::make('session_lifetime')
                            ->label('Admin Session Lifetime (Minutes)')
                            ->numeric()->minValue(15)->maxValue(1440)->required()
                            ->helperText('Minutes of inactivity before the admin panel session expires')
                            ->default(120),
                    ])->columns(2),

                Section::make('Audit Log Retention')
                    ->description('How long security-relevant logs are kept before the daily GDPR cleanup job permanently deletes them.')
                    ->schema([
                        Forms\Components\TextInput::make('log_retention_days')
                            ->label('Log Retention (Days)')
                            ->numeric()->minValue(1)->maxValue(365)->required()
                            ->helperText('Applies to login attempts, admin activity history, search/failed-search logs, cron logs, and email logs. Lowering this shortens how far back you can investigate a security incident or admin action — do not lower it just to save database space.')
                            ->default(90),
                    ])->columns(2),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Multi-group overrides — copied verbatim from SeoControlCenter.php,
    // the only prior precedent for a SettingsPage spanning >1 settings
    // group. SettingsPage's base implementation assumes a single
    // static::$settingsGroup throughout; every method below loops over
    // static::$settingsGroups instead. See class docblock.
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
