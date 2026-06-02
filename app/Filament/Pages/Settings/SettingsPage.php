<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Clusters\Settings as SettingsCluster;
use App\Models\Setting;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;

abstract class SettingsPage extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static string $settingsGroup;

    protected static bool $shouldRegisterNavigation = false;

    public array $data = [];

    public function mount(): void
    {
        $this->fillForm();
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
                    $settings[$key] = Crypt::decryptString($value);
                } catch (\Exception $e) {
                }
            }
        }

        $this->form->fill($settings);
    }

    public function save(): void
    {
        $this->validate();

        $service = app(SettingsService::class);

        foreach ($this->data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (is_array($value)) {
                $value = json_encode($value);
            }

            if ($this->isEncryptedField($key) && $value && !str_starts_with($value, 'eyJ')) {
                $value = Crypt::encryptString($value);
            }

            $service->set(static::$settingsGroup . '.' . $key, $value);
        }

        Notification::make()
            ->title('Settings saved')
            ->body('Cache cleared for group: ' . static::$settingsGroup)
            ->success()
            ->send();
    }

    protected function isEncryptedField(string $key): bool
    {
        return in_array($key, [
            'smtp_password',
            'airwallex_api_key',
            'airwallex_webhook_secret',
            'ga_api_secret',
            'currency_exchange_api_key',
        ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cog-6-tooth';
    }
}
