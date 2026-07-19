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

    /**
     * Every settings page previously had only Save/Discard/Reset in the form
     * footer — no way back to the Settings overview except the small,
     * easy-to-miss cluster breadcrumb link above the header. Subclasses that
     * override this for their own header actions (EmailSettings,
     * PaymentSettings, PerformanceSettings) merge this in via
     * `...parent::getHeaderActions()` rather than replacing it.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToSettings')
                ->label('Back to Settings')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(SettingsCluster::getUrl()),
        ];
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

    /**
     * Saves immediately on click — a single, direct "Save" button, not a
     * preview-then-confirm flow. The diff is still computed (buildChangesDiff)
     * and written to the activity log below; only the extra "review before
     * you commit" click was removed, per explicit product decision — settings
     * pages should behave like a normal edit form, not a two-step wizard.
     */
    public function save(): void
    {
        $this->validate();

        $oldValues = Setting::where('group', static::$settingsGroup)
            ->pluck('value', 'key')
            ->toArray();

        $changed = $this->buildChangesDiff($oldValues);

        if (empty($changed)) {
            Notification::make()
                ->title('No changes detected')
                ->info()
                ->send();

            return;
        }

        $this->persistChanges($oldValues);

        Notification::make()
            ->title('Settings saved')
            ->body('Cache cleared for group: '.static::$settingsGroup)
            ->success()
            ->send();
    }

    /** Writes $this->data to the settings table and records the before/after in the activity log. */
    private function persistChanges(array $oldValues): void
    {
        $service = app(SettingsService::class);
        $admin = auth('admin')->user();

        foreach ($this->data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (is_array($value)) {
                // Same empty-array normalization as buildDiffBetween() — an
                // untouched FileUpload/CheckboxList/KeyValue field's "nothing
                // selected" state is []; without this, saving ANY real
                // change elsewhere on the same page would also silently
                // overwrite this field's stored "" with the literal string
                // "[]" (persistChanges() writes every key in $this->data, not
                // just the ones that changed).
                $value = empty($value) ? '' : json_encode($value);
            }

            $service->set(static::$settingsGroup . '.' . $key, $value);

            // Compare against the already-string-normalized $value, not the
            // original raw field value — for array-typed fields that used to
            // mean (string) $rawArray, which throws "Array to string
            // conversion" (a warning in production, but a hard failure under
            // this project's test error-reporting) on every single save of
            // any settings page with a FileUpload/CheckboxList/KeyValue
            // field, confirmed live.
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
                // An empty array is FileUpload/CheckboxList/KeyValue's "nothing
                // selected" Livewire state — json_encode([]) is the string
                // "[]", but a genuinely unset setting is stored as "". Without
                // this, every settings page with an untouched array-typed
                // field always reported a phantom change ("" -> "[]") on
                // every single Save click, confirmed live via the raw
                // Livewire response payload (GeneralSettings' logo_id/
                // favicon_id) — never "No changes detected", and confirming
                // it would have written the literal string "[]" into the
                // setting, breaking whatever reads it expecting empty/null.
                $raw = empty($value) ? '' : json_encode($value);
                $display = empty($value) ? '—' : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                $raw = $value;
                $display = (string) $value;
            }

            $oldRaw = $oldValues[$key] ?? null;
            $isEncrypted = in_array($key, $encryptedKeys);

            // Toggle fields always re-serialize their live state as the
            // literal strings "true"/"false" (see $raw above), but every
            // single boolean setting in SettingsSeeder is seeded as "1"/"0"
            // — a plain string comparison ("1" !== "true") therefore flagged
            // EVERY Toggle-backed setting as changed on EVERY page load,
            // confirmed live (SEOSettings' google_ping_enabled, seeded "1",
            // always diffed against the untouched form's "true"). The rest
            // of the app already treats both spellings as equivalent via
            // filter_var(settings(...), FILTER_VALIDATE_BOOLEAN) — the diff
            // must use the same equivalence, not a literal string match.
            if (is_bool($value)) {
                $hasChanged = filter_var($oldRaw, FILTER_VALIDATE_BOOLEAN) !== $value;
            } elseif (is_array($value)) {
                // Compare DECODED values, not re-encoded JSON strings. The
                // seeded JSON in the settings table is inconsistently
                // escaped across rows — some multilang values were seeded
                // with escaped \uXXXX unicode sequences, others with the
                // raw UTF-8 characters (e.g. a literal "…" byte vs "…")
                // — a byte-for-byte string comparison of two re-encodings
                // can never be made to agree with ALL of them at once
                // (confirmed live: fixing one seeded-encoding style broke
                // the other). Decoding both sides first sidesteps escaping,
                // key-order, and whitespace differences entirely — two
                // arrays with the same content are equal regardless of how
                // either was originally serialized.
                $oldDecoded = is_string($oldRaw)
                    ? (json_decode($oldRaw, true) ?? ($oldRaw === '' ? [] : null))
                    : ($oldRaw === null ? [] : $oldRaw);
                $hasChanged = $oldDecoded === null || $oldDecoded !== $value;
            } else {
                $hasChanged = (string) ($oldRaw ?? '') !== (string) $raw;

                // A numeric TextInput's live value silently reformats on
                // hydration ("0.00" seeded -> live state "0", "10.00" -> "10"
                // — confirmed live on ShippingSettings' handling_fee/
                // nudge_threshold), and TimePicker always re-serializes with
                // seconds ("15:00" seeded -> live "15:00:00" — cutoff_time).
                // Both phantom-diffed on every load. Numerically/temporally
                // equal values must not count as changed just because their
                // string formatting differs.
                if ($hasChanged && $oldRaw !== null && is_numeric($oldRaw) && is_numeric($raw)) {
                    $hasChanged = bccomp((string) $oldRaw, (string) $raw, 6) !== 0;
                } elseif ($hasChanged && is_string($oldRaw) && is_string($raw)
                    && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $oldRaw)
                    && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $raw)
                ) {
                    $hasChanged = date('H:i:s', strtotime($oldRaw)) !== date('H:i:s', strtotime($raw));
                }
            }

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

        // A single, direct Save button — saves immediately on click, no
        // preview/confirm step. (Reset to Defaults above stays two-step: it's
        // a bulk overwrite of the whole group, materially more destructive
        // than a normal field edit.)
        return [
            Action::make('save')
                ->label('Save')
                ->icon('heroicon-o-check')
                ->submit('save')
                ->color('success'),

            Action::make('resetToDefaults')
                ->label('Reset to Defaults')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->outlined()
                ->action('resetToDefaults'),
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
