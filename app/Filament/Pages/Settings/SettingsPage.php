<?php

namespace App\Filament\Pages\Settings;

use App\Enums\SettingType;
use App\Filament\Clusters\Settings as SettingsCluster;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Setting;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
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

    /**
     * Note: $navigationSort on subclasses is NOT consulted for display order.
     * shouldRegisterNavigation=false hides every settings page from Filament's
     * nav tree entirely; reachability and display order are driven by
     * App\Filament\Support\SettingsRegistry::PAGES instead. A new settings
     * page must be added to that registry to be reachable at all — but
     * tests/Feature/SettingsRegistryTest.php asserts every concrete
     * SettingsPage subclass has exactly one registry entry, so a forgotten
     * page now fails a test instead of silently going unreachable (this
     * happened to UISettings before the registry existed — see
     * ARCHITECTURE.md's Settings Architecture section). Keep each subclass's
     * $navigationSort unique anyway for documentation accuracy.
     */
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

    /**
     * Mirrors SettingsCluster::canAccess() — the cluster's own gate does not
     * cascade to child pages (Filament only consults it to decide whether the
     * cluster itself appears in navigation), so every subclass must inherit
     * this override rather than rely on Filament's CanAuthorizeAccess default
     * (which allows any authenticated panel user).
     */
    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
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

    /**
     * Derived directly from SettingsSeeder::definitions() — the actual
     * source of truth that populates the database — rather than a
     * separately hand-maintained list. A hardcoded duplicate here is
     * exactly how 18 of 30 groups drifted (some down to zero protection;
     * see ADMIN_PANEL_MASTER_WORKFLOW.md Option TT) before this refactor.
     */
    protected function getFactoryDefaults(): array
    {
        return collect(SettingsSeeder::definitions())
            ->where('group', static::$settingsGroup)
            ->mapWithKeys(fn (array $row) => [$row['key'] => static::castDefinitionValue($row['value'], $row['type'])])
            ->all();
    }

    private static function castDefinitionValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            SettingType::Boolean->value => in_array($value, ['1', 1, true], true),
            SettingType::Integer->value => (int) $value,
            SettingType::Decimal->value => (float) $value,
            SettingType::Json->value => json_decode($value, true) ?? [],
            default => $value, // string, encrypted — encrypted defaults are seeded as empty placeholders, never decrypted here
        };
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cog-6-tooth';
    }
}
