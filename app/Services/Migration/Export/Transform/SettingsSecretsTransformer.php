<?php

namespace App\Services\Migration\Export\Transform;

use App\Support\EncryptedSettingValue;

/**
 * Settings that hold secrets (SMTP password, gateway keys, tokens) are encrypted with the source site's
 * APP_KEY, so a copy of the row is useless on another server anyway. What happens to them is the choice
 * of the person exporting:
 *
 *  - LEFT OUT (the default): the value is blanked without ever being decrypted; the row stays, so the
 *    target knows the setting exists, and the key names go into the report as "re-enter these".
 *  - INCLUDED: each value is decrypted with the source key (APP_PREVIOUS_KEYS honoured) and handed to
 *    `$secretSink`, which writes it to the package's separately-keyed secrets entry. The row in the main
 *    stream stays blank either way. A value that cannot be decrypted is blanked and reported; its
 *    ciphertext is never emitted, because it would look like a configured credential on the target.
 *
 * A row is a secret if `is_encrypted = 1` or `type = 'encrypted'`. Rows are read raw (DB::table), never
 * through SettingsService, whose cache and decrypt fallbacks are exactly what must not be involved here.
 */
final class SettingsSecretsTransformer implements RowTransformer
{
    /** @var array<int, string> */
    private array $configured = [];

    /** @var array<int, string> */
    private array $included = [];

    /** @var array<int, string> */
    private array $undecryptable = [];

    /** @param  \Closure(array{group: string, key: string, value: string}): void|null  $secretSink */
    public function __construct(
        private readonly bool $includeSecrets,
        private readonly ?\Closure $secretSink = null,
    ) {
        if ($includeSecrets && $secretSink === null) {
            throw new \InvalidArgumentException('Including secrets needs somewhere to put them.');
        }
    }

    public function tables(): array
    {
        return ['settings'];
    }

    public function transform(string $table, array $row): array
    {
        if (! $this->isSecret($row)) {
            return $row;
        }

        $stored = (string) ($row['value'] ?? '');
        $label = ($row['group'] ?? '').'.'.($row['key'] ?? '');

        // Nothing configured: nothing to hide, nothing to ask the person to re-enter.
        if ($stored === '') {
            return $row;
        }

        if ($this->includeSecrets) {
            $revealed = EncryptedSettingValue::reveal($stored, $label, quiet: true);

            if ($revealed->ok) {
                ($this->secretSink)(['group' => (string) $row['group'], 'key' => (string) $row['key'], 'value' => $revealed->value]);
                $this->included[] = $label;
            } else {
                $this->undecryptable[] = $label;
            }
        } else {
            $this->configured[] = $label;
        }

        $row['value'] = '';

        return $row;
    }

    /**
     * @return array{configured_keys: array<int, string>, included_keys: array<int, string>, undecryptable_keys: array<int, string>}
     */
    public function report(): array
    {
        return [
            'configured_keys' => $this->configured,
            'included_keys' => $this->included,
            'undecryptable_keys' => $this->undecryptable,
        ];
    }

    /** @param  array<string, mixed>  $row */
    public function isSecret(array $row): bool
    {
        return (int) ($row['is_encrypted'] ?? 0) === 1 || ($row['type'] ?? null) === 'encrypted';
    }
}
