<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Clusters\Settings as SettingsCluster;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Setting;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;

abstract class SettingsPage extends Page
{
    use HasUnsavedDataChangesAlert;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string $settingsGroup;

    protected static bool $shouldRegisterNavigation = false;

    public array $data = [];

    public ?array $pendingChanges = null;

    public bool $resetMode = false;

    public function mount(): void
    {
        $this->fillForm();
        $this->mountHasUnsavedDataChangesAlert();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->sticky($this->areFormActionsSticky())
                    ->key('form-actions'),
            ]);
    }

    protected function fillForm(): void
    {
        $settings = Setting::where('group', static::$settingsGroup)
            ->pluck('value', 'key')
            ->toArray();

        foreach ($settings as $key => $value) {
            $setting = Setting::where('group', static::$settingsGroup)
                ->where('key', $key)
                ->first();

            if ($setting && $setting->is_encrypted && $value) {
                try {
                    $value = Crypt::decryptString($value);
                    $settings[$key] = $value;
                } catch (\Exception $e) {
                \Log::warning("Failed to decrypt setting {$key}: " . $e->getMessage());
            }
            }

            if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $settings[$key] = $decoded;
                }
            }
        }

        $this->form->fill($settings);
    }

    public function save(): void
    {
        $this->validate();

        $oldValues = Setting::where('group', static::$settingsGroup)
            ->pluck('value', 'key')
            ->toArray();

        $changed = $this->buildChangesDiff($oldValues);

        if (empty($changed)) {
            $this->pendingChanges = null;
            Notification::make()
                ->title('No changes detected')
                ->info()
                ->send();

            return;
        }

        $this->pendingChanges = [
            'oldValues' => $oldValues,
            'changed' => $changed,
        ];
    }

    public function confirmSave(): void
    {
        if ($this->pendingChanges === null) {
            return;
        }

        $service = app(SettingsService::class);
        $oldValues = $this->pendingChanges['oldValues'];
        $admin = auth('admin')->user();

        foreach ($this->data as $key => $value) {
            $raw = $value;

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (is_array($value)) {
                $value = json_encode($value);
            }

            $service->set(static::$settingsGroup . '.' . $key, $value);

            if (array_key_exists($key, $oldValues) && (string) ($oldValues[$key] ?? '') !== (string) $raw) {
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

        Notification::make()
            ->title('Settings saved')
            ->body('Cache cleared for group: ' . static::$settingsGroup)
            ->success()
            ->send();
    }

    protected function afterSave(): void
    {
    }

    public function cancelSave(): void
    {
        $this->pendingChanges = null;

        Notification::make()
            ->title('Changes discarded')
            ->info()
            ->send();
    }

    public function resetToDefaults(): void
    {
        $defaults = $this->getFactoryDefaults();

        if (empty($defaults)) {
            Notification::make()
                ->title('No factory defaults defined')
                ->body('This settings group does not have factory defaults configured.')
                ->warning()
                ->send();

            return;
        }

        $oldValues = Setting::where('group', static::$settingsGroup)
            ->pluck('value', 'key')
            ->toArray();

        $changed = $this->buildDiffBetween($oldValues, $defaults);

        if (empty($changed)) {
            Notification::make()
                ->title('Settings already at defaults')
                ->info()
                ->send();

            return;
        }

        $this->pendingChanges = [
            'oldValues' => $oldValues,
            'changed' => $changed,
            'resetDefaults' => $defaults,
        ];
        $this->resetMode = true;
    }

    public function confirmReset(): void
    {
        if ($this->pendingChanges === null || ! isset($this->pendingChanges['resetDefaults'])) {
            return;
        }

        $this->data = array_merge($this->data, $this->pendingChanges['resetDefaults']);
        $this->pendingChanges = null;

        $this->resetMode = false;

        Notification::make()
            ->title('Defaults loaded')
            ->body('Review the changes and save to persist.')
            ->success()
            ->send();
    }

    protected function buildChangesDiff(array $oldValues): array
    {
        return $this->buildDiffBetween($oldValues, $this->data);
    }

    protected function buildDiffBetween(array $oldValues, array $newValues): array
    {
        $changed = [];
        $encryptedKeys = $this->getEncryptedKeys();

        foreach ($newValues as $key => $value) {
            if (is_bool($value)) {
                $raw = $value ? 'true' : 'false';
                $display = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $raw = json_encode($value, JSON_UNESCAPED_UNICODE);
                $display = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                $raw = $value;
                $display = (string) $value;
            }

            $oldRaw = $oldValues[$key] ?? null;
            $isEncrypted = in_array($key, $encryptedKeys);
            $hasChanged = (string) ($oldRaw ?? '') !== (string) $raw;

            if (! $hasChanged) {
                continue;
            }

            $changed[$key] = [
                'old'    => $isEncrypted && $oldRaw ? '***' : (string) ($oldRaw ?? '—'),
                'new'    => $isEncrypted && $raw ? '***' : $display,
                'masked' => $isEncrypted,
            ];
        }

        return $changed;
    }

    protected function getEncryptedKeys(): array
    {
        return Setting::where('group', static::$settingsGroup)
            ->where('is_encrypted', true)
            ->pluck('key')
            ->toArray();
    }

    protected function getFormActions(): array
    {
        if ($this->pendingChanges !== null && isset($this->pendingChanges['resetDefaults'])) {
            return [
                Action::make('confirm_reset')
                    ->label('Apply Defaults (' . count($this->pendingChanges['changed']) . ' changes)')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->action('confirmReset')
                    ->requiresConfirmation()
                    ->modalHeading('Reset to Factory Defaults')
                    ->modalContent(fn (): string => view('components.settings-diff-table', [
                        'changes' => $this->pendingChanges['changed'],
                        'heading' => 'The following settings will be restored to factory defaults:',
                    ])->render())
                    ->modalIcon('heroicon-o-arrow-uturn-left')
                    ->modalSubmitActionLabel('Yes, Apply Defaults'),

                Action::make('discard_reset')
                    ->label('Cancel')
                    ->color('gray')
                    ->icon('heroicon-o-x-mark')
                    ->outlined()
                    ->action('cancelSave'),
            ];
        }

        if ($this->pendingChanges !== null) {
            return [
                Action::make('confirm_save')
                    ->label('Confirm Save (' . count($this->pendingChanges['changed']) . ' changes)')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->action('confirmSave')
                    ->requiresConfirmation()
                    ->modalHeading('Save Settings Changes')
                    ->modalContent(fn (): string => view('components.settings-diff-table', [
                        'changes' => $this->pendingChanges['changed'],
                        'heading' => 'The following settings changes will be applied:',
                    ])->render())
                    ->modalIcon('heroicon-o-eye')
                    ->modalSubmitActionLabel('Yes, Apply Changes'),

                Action::make('discard_changes')
                    ->label('Discard Changes')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->outlined()
                    ->action('cancelSave'),
            ];
        }

        return [
            ActionGroup::make([
                Action::make('save')
                    ->label('Preview Changes')
                    ->submit('save')
                    ->color('success'),

                Action::make('resetToDefaults')
                    ->label('Reset to Defaults')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->outlined()
                    ->action('resetToDefaults'),
            ])
                ->label('Save Actions')
                ->button(),
        ];
    }

    protected function getFactoryDefaults(): array
    {
        return match (static::$settingsGroup) {
            'general' => [
                'site_name' => 'OeParts',
                'site_url' => config('app.url', 'http://localhost'),
                'default_locale' => 'en',
                'timezone' => 'UTC',
                'date_format' => 'Y-m-d',
                'currency' => 'EUR',
                'currency_symbol' => '€',
                'tagline' => 'Premium OEM Auto Parts',
            ],
            'tax' => [
                'default_vat_rate' => '21',
                'price_display' => 'inc_vat',
                'vat_validation_enabled' => false,
                'b2b_exempt_on_valid_vat' => false,
            ],
            'shipping' => [
                'free_shipping_threshold' => '200',
                'handling_fee' => '0',
                'nudge_enabled' => true,
                'default_origin_country' => 'DE',
            ],
            'auth' => [
                'otp_length' => 6,
                'otp_expiry_minutes' => 10,
                'otp_max_attempts' => 5,
                'otp_resend_cooldown' => 30,
                'customer_password_min' => 8,
                'admin_password_min' => 12,
                'customer_session_lifetime' => 120,
                'guest_checkout_enabled' => true,
                'registration_enabled' => true,
            ],
            'email' => [
                'smtp_port' => '587',
                'smtp_encryption' => 'tls',
                'admin_notify_new_order' => true,
                'admin_notify_new_inquiry' => true,
            ],
            'checkout' => [
                'timeout_minutes' => 30,
                'max_steps' => 3,
                'max_note_length' => 500,
                'default_payment_method' => 'card',
            ],
            'payment' => [
                'airwallex_environment' => 'demo',
                'card_enabled' => true,
                'bank_transfer_enabled' => true,
                'bank_reference_prefix' => 'OEP-',
            ],
            'store' => [
                'timezone' => 'UTC',
                'date_format' => 'Y-m-d',
                'currency' => 'EUR',
                'currency_symbol' => '€',
                'decimals' => '2',
            ],
            'company' => [
                'company_name' => 'OeParts',
                'company_reg_number' => '',
                'company_vat_number' => '',
                'company_address' => '',
                'company_bank_name' => '',
                'company_bank_iban' => '',
            ],
            'contact' => [
                'email' => 'info@oeparts.lt',
                'phone' => '+370 600 00000',
                'address' => '',
                'contact_form_enabled' => true,
                'contact_form_recipient' => 'info@oeparts.lt',
                'map_latitude' => '',
                'map_longitude' => '',
                'map_zoom' => '12',
                'working_hours' => '',
            ],
            'menu' => [
                'menu_style' => 'modern',
                'enable_mega_menu' => false,
                'sticky_header' => true,
                'mobile_breakpoint' => '1024',
            ],
            'social_links' => [
                'facebook_url' => '',
                'instagram_url' => '',
                'youtube_url' => '',
                'linkedin_url' => '',
            ],
            'stats_counter' => [
                'products_count' => '',
                'customers_count' => '',
                'years_in_business' => '',
                'show_products' => true,
                'show_customers' => true,
                'show_years' => true,
            ],
            'integrations' => [
                'gtm_id' => '',
                'ga4_measurement_id' => '',
                'fb_pixel_id' => '',
                'recaptcha_site_key' => '',
                'recaptcha_secret_key' => '',
            ],
            'search' => [
                'min_chars' => 3,
                'autocomplete_count' => 8,
                'rate_limit_per_minute' => 60,
                'max_results' => 50,
                'log_searches' => true,
                'log_failed' => true,
                'log_retention_days' => 30,
                'cross_ref_enabled' => true,
                'partial_match_enabled' => true,
                'partial_match_min_length' => 4,
            ],
            'part_inquiry' => [
                'response_hours' => 48,
                'guest_inquiries_allowed' => true,
                'rate_limit_per_hour' => 10,
            ],
            'performance' => [
                'cache_driver' => 'redis',
                'cache_ttl_settings' => 5,
                'cache_ttl_sections' => 60,
                'cache_ttl_manufacturers' => 1440,
                'cache_settings' => true,
                'cache_sections' => true,
                'cache_manufacturers' => true,
            ],
            'newsletter' => [
                'rate_limit_per_hour' => 30,
                'rate_window_seconds' => 3600,
                'double_opt_in' => true,
            ],
            'security' => [
                'login_max_attempts' => 5,
                'login_window_minutes' => 15,
                'inquiry_max_per_email' => 5,
                'ip_blocklist_enabled' => true,
                'honeypot_enabled' => true,
                'csrf_enabled' => true,
                'force_https' => true,
                'admin_2fa_required' => false,
            ],
            'maintenance' => [
                'enabled' => false,
                'show_estimated_time' => true,
            ],
            'sections' => [
                'testimonials_limit' => 5,
                'faq_limit' => 10,
                'blog_limit' => 6,
                'manufacturers_limit' => 12,
            ],
            'announcement' => [
                'enabled' => false,
                'dismissable' => true,
                'color' => 'warning',
            ],
            'appearance' => [
                'primary_color' => '#1d4ed8',
                'custom_css_enabled' => false,
            ],
            'orders' => [
                'bank_transfer_expiry_hours' => 48,
                'customer_cancel_window_hours' => 24,
                'refund_window_days' => 30,
                'minimum_order_amount' => '0',
                'auto_complete_days' => 14,
                'urgent_processing_enabled' => false,
                'urgent_processing_fee' => '0',
                'order_number_prefix' => 'OEP-',
                'order_number_padding' => 6,
                'invoice_number_prefix' => 'INV-',
                'rma_number_prefix' => 'RMA-',
            ],
            'cart' => [
                'expiry_days' => 30,
                'max_items' => 50,
                'checkout_timeout_minutes' => 30,
                'otp_required_guest' => true,
                'coupon_enabled' => true,
                'merge_on_login' => true,
            ],
            'seo' => [
                'default_robots' => 'index,follow',
                'maintenance_noindex' => true,
                'google_ping_enabled' => true,
                'sitemap_search_log_days' => 7,
            ],
            'preloader' => [
                'enabled' => true,
                'min_display_ms' => 300,
                'max_display_ms' => 3000,
            ],
            default => [],
        };
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cog-6-tooth';
    }
}
