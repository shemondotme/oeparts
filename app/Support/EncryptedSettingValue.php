<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * The ONE place that turns a stored, APP_KEY-encrypted settings value back into
 * plain text.
 *
 * A stored secret can become unreadable: the site was restored or migrated onto
 * a server with a different APP_KEY, the key was rotated, or the ciphertext was
 * corrupted. In every one of those cases the caller must receive an EMPTY value
 * and ok=false — never the raw ciphertext. Before this class, SettingsService
 * kept the ciphertext as the value, so an unreadable SMTP password or gateway
 * key looked like a configured credential (empty() checks passed, the settings
 * form showed it, and saving the form encrypted the ciphertext a second time).
 *
 * Nothing outside this class may call Crypt::decryptString() on a Setting row;
 * EncryptedSettingValueGuardTest enforces that.
 */
final class EncryptedSettingValue
{
    private function __construct(
        public readonly string $value,
        public readonly bool $ok,
    ) {}

    /**
     * @param  string  $label  "group.key" or just the key, used only in the log line
     * @param  bool  $quiet  skip the warning (for bulk scans that would repeat it every few minutes)
     */
    public static function reveal(?string $stored, string $label = '', bool $quiet = false): self
    {
        if ($stored === null || $stored === '') {
            return new self('', true);
        }

        try {
            return new self(Crypt::decryptString($stored), true);
        } catch (\Throwable $e) {
            if (! $quiet) {
                Log::warning("Failed to decrypt setting {$label}: ".$e->getMessage());
            }

            return new self('', false);
        }
    }

    /**
     * One warning for a settings page whose form was filled while some secrets
     * could not be read, so the admin knows those fields are blank on purpose.
     *
     * @param  array<int, string>  $keys
     */
    public static function warnUnreadable(array $keys): void
    {
        if ($keys === []) {
            return;
        }

        Notification::make()
            ->title('Some saved secrets could not be read')
            ->body('This server cannot decrypt: '.implode(', ', $keys).'. The fields are shown empty. Enter them again and save.')
            ->warning()
            ->persistent()
            ->send();
    }
}
