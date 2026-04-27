<?php

namespace App\Services;

use App\Enums\SettingType;
use App\Models\Setting;

/**
 * Seeds the `ui` group with overridable storefront copy from en lang files and navbar defaults.
 */
class UiCopyInstaller
{
    /**
     * @return array{rows: int, groups: int}
     */
    public function installSettings(): array
    {
        $j = SettingType::Json->value;
        $langs = ['en', 'de', 'lt', 'fr', 'es'];
        $ml = static fn (string $text) => json_encode(array_fill_keys($langs, $text), JSON_UNESCAPED_UNICODE);
        $count = 0;

        foreach (self::stringsFromFile(base_path('lang/en/search.php'), 'search_') as $key => $value) {
            $this->upsertUi($key, $value, $j, $ml);
            $count++;
        }

        foreach (self::stringsFromFile(base_path('lang/en/cart.php'), 'cart_') as $key => $value) {
            $this->upsertUi($key, $value, $j, $ml);
            $count++;
        }

        foreach (self::stringsFromFile(base_path('lang/en/navbar.php'), 'nav_') as $key => $value) {
            $this->upsertUi($key, $value, $j, $ml);
            $count++;
        }

        app(SettingsService::class)->forget('ui');

        return ['rows' => $count, 'groups' => 1];
    }

    public static function installedUiKeyPrefixes(): array
    {
        $keys = [];
        foreach (array_keys(self::stringsFromFile(base_path('lang/en/search.php'), 'search_')) as $k) {
            $keys[] = $k;
        }
        foreach (array_keys(self::stringsFromFile(base_path('lang/en/cart.php'), 'cart_')) as $k) {
            $keys[] = $k;
        }
        foreach (array_keys(self::stringsFromFile(base_path('lang/en/navbar.php'), 'nav_')) as $k) {
            $keys[] = $k;
        }

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    private static function stringsFromFile(string $path, string $keyPrefix): array
    {
        if (! is_file($path)) {
            return [];
        }
        $data = require $path;
        if (! is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && is_string($v) && $v !== '') {
                $out[$keyPrefix.$k] = $v;
            }
        }

        return $out;
    }

    private function upsertUi(string $key, string $value, string $type, callable $ml): void
    {
        $encoded = $ml($value);
        Setting::updateOrCreate(
            ['group' => 'ui', 'key' => $key],
            [
                'value' => $encoded,
                'type' => $type,
                'is_encrypted' => false,
            ]
        );
    }
}
