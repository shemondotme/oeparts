<?php

namespace App\Services;

use App\Enums\SettingType;
use App\Models\ActivityLog;
use App\Models\Setting;

/**
 * Moves the SEO Control Center's settings (the 'seo' and 'crawlers' groups) between installs as a JSON file —
 * set up the staging copy once and apply it to the live shop, or keep a known-good configuration to roll back to.
 *
 * Never travels: secrets (anything encrypted — API keys, tokens) and values that belong to one particular site
 * (its domain, its Search Console property, its verification codes, its image ids). An import is checked entry by
 * entry — unknown keys and values of the wrong kind are refused, not guessed at — and is shown as a list of
 * changes before anything is written.
 */
final class SeoSettingsTransfer
{
    public const FORMAT = 'oeparts-seo-settings';

    public const VERSION = 1;

    public const GROUPS = ['seo', 'crawlers'];

    /** Belong to one site; exporting them would point the other install at this one's accounts and address. */
    public const SITE_SPECIFIC = [
        'canonical_host', 'gsc_client_id', 'gsc_property_url', 'google_verification', 'bing_verification', 'default_og_image',
    ];

    /** @return array{format: string, version: int, exported_at: string, settings: array<string, mixed>} */
    public function export(): array
    {
        $settings = [];

        foreach ($this->transferable() as $setting) {
            $settings[$setting->key] = $this->typed($setting);
        }

        ksort($settings);

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'settings' => $settings,
        ];
    }

    /**
     * What importing this file would do — nothing is written.
     *
     * @return array{
     *     changes: list<array{key: string, old: string, new: string}>,
     *     unchanged: int,
     *     refused: list<array{key: string, reason: string}>,
     *     error: ?string,
     *     values: array<string, string>
     * }
     */
    public function preview(mixed $payload): array
    {
        $result = ['changes' => [], 'unchanged' => 0, 'refused' => [], 'error' => null, 'values' => []];

        if (! is_array($payload) || ($payload['format'] ?? null) !== self::FORMAT || ! is_array($payload['settings'] ?? null)) {
            $result['error'] = 'This is not an SEO settings file exported from here.';

            return $result;
        }

        if ((int) ($payload['version'] ?? 0) > self::VERSION) {
            $result['error'] = 'This file comes from a newer version — update this site first.';

            return $result;
        }

        $known = $this->transferable()->keyBy('key');

        foreach ($payload['settings'] as $key => $value) {
            $key = (string) $key;
            $setting = $known->get($key);

            if (! $setting) {
                $result['refused'][] = ['key' => $key, 'reason' => in_array($key, self::SITE_SPECIFIC, true)
                    ? 'belongs to one site and is never imported'
                    : 'not a setting this site has (or it holds a secret)'];

                continue;
            }

            $normalized = $this->normalize($setting, $value);
            if ($normalized === null) {
                $result['refused'][] = ['key' => $key, 'reason' => 'the value is not a valid '.$setting->type->value];

                continue;
            }

            $current = (string) ($setting->value ?? '');
            if ($this->same($setting, $current, $normalized)) {
                $result['unchanged']++;

                continue;
            }

            $result['changes'][] = ['key' => $key, 'old' => $this->shorten($current), 'new' => $this->shorten($normalized)];
            $result['values'][$key] = $normalized;
        }

        return $result;
    }

    /**
     * Writes the changes preview() found. Returns how many settings changed.
     */
    public function apply(mixed $payload): int
    {
        $preview = $this->preview($payload);

        if ($preview['error'] !== null || $preview['values'] === []) {
            return 0;
        }

        $service = app(SettingsService::class);
        $old = [];
        foreach ($preview['values'] as $key => $value) {
            $setting = Setting::whereIn('group', self::GROUPS)->where('key', $key)->first();
            $old[$key] = (string) ($setting?->value ?? '');
            $service->set($setting->group.'.'.$key, $value);
        }

        if ($admin = auth('admin')->user()) {
            ActivityLog::create([
                'admin_id' => $admin->id,
                'action' => 'settings_imported',
                'model_type' => Setting::class,
                'model_id' => null,
                'old_values' => $old,
                'new_values' => $preview['values'],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        }

        return count($preview['values']);
    }

    // ── internals ─────────────────────────────────────────────────────────

    /** @return \Illuminate\Database\Eloquent\Collection<int, Setting> */
    private function transferable()
    {
        return Setting::query()
            ->whereIn('group', self::GROUPS)
            ->where('is_encrypted', false)
            ->where('type', '!=', SettingType::Encrypted->value)
            ->whereNotIn('key', self::SITE_SPECIFIC)
            ->orderBy('key')
            ->get();
    }

    /** The value as its natural type, so the file reads as JSON should (true, 100, a list…). */
    private function typed(Setting $setting): mixed
    {
        $raw = (string) ($setting->value ?? '');

        return match ($setting->type) {
            SettingType::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            SettingType::Integer => (int) $raw,
            SettingType::Decimal => (float) $raw,
            SettingType::Json => $raw === '' ? null : (json_decode($raw, true) ?? $raw),
            default => $raw,
        };
    }

    /** The string to store for $value, or null when it is not a valid value for this setting's type. */
    private function normalize(Setting $setting, mixed $value): ?string
    {
        switch ($setting->type) {
            case SettingType::Boolean:
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }
                if (in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    return in_array($value, [1, '1', 'true'], true) ? 'true' : 'false';
                }

                return null;

            case SettingType::Integer:
                return (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) ? (string) (int) $value : null;

            case SettingType::Decimal:
                return is_numeric($value) ? (string) $value : null;

            case SettingType::Json:
                if ($value === null) {
                    return '';
                }
                if (is_string($value)) {
                    return json_decode($value) !== null || $value === 'null' ? $value : null;
                }

                return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

            default:
                return is_string($value) && mb_strlen($value) <= 5000 ? $value : (is_scalar($value) && ! is_bool($value) && mb_strlen((string) $value) <= 5000 ? (string) $value : null);
        }
    }

    private function same(Setting $setting, string $current, string $new): bool
    {
        return match ($setting->type) {
            SettingType::Boolean => filter_var($current, FILTER_VALIDATE_BOOLEAN) === filter_var($new, FILTER_VALIDATE_BOOLEAN),
            SettingType::Json => json_decode($current, true) === json_decode($new, true) && ($current === '') === ($new === ''),
            default => $current === $new,
        };
    }

    private function shorten(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? '—' : (mb_strlen($value) > 120 ? mb_substr($value, 0, 117).'…' : $value);
    }
}
