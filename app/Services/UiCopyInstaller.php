<?php

namespace App\Services;

use App\Enums\SettingType;
use App\Models\Setting;

/**
 * Seeds the `ui` group with overridable storefront copy pulled from en lang
 * files. Each install*() method processes its own fixed file/prefix map so
 * a migration's down() only ever deletes the keys its own up() added —
 * installedUiKeyPrefixes() must stay scoped the same way installSettings()
 * is, not a single combined list, or rolling back one migration would
 * delete rows a later migration seeded too.
 */
class UiCopyInstaller
{
    private const SEARCH_CART_NAV = [
        ['file' => 'search.php', 'prefix' => 'search_'],
        ['file' => 'cart.php', 'prefix' => 'cart_'],
        ['file' => 'navbar.php', 'prefix' => 'nav_'],
    ];

    private const CHECKOUT_ACCOUNT_FOOTER = [
        ['file' => 'checkout.php', 'prefix' => 'checkout_'],
        ['file' => 'account.php', 'prefix' => 'account_'],
        ['file' => 'footer.php', 'prefix' => 'footer_'],
    ];

    /**
     * @return array{rows: int, groups: int}
     */
    public function installSettings(): array
    {
        return $this->installFromMap(self::SEARCH_CART_NAV);
    }

    /**
     * @return array{rows: int, groups: int}
     */
    public function installCheckoutAccountFooterSettings(): array
    {
        return $this->installFromMap(self::CHECKOUT_ACCOUNT_FOOTER);
    }

    public static function installedUiKeyPrefixes(): array
    {
        return self::keysFromMap(self::SEARCH_CART_NAV);
    }

    public static function installedCheckoutAccountFooterUiKeyPrefixes(): array
    {
        return self::keysFromMap(self::CHECKOUT_ACCOUNT_FOOTER);
    }

    /**
     * @param  array<int, array{file: string, prefix: string}>  $map
     * @return array{rows: int, groups: int}
     */
    private function installFromMap(array $map): array
    {
        $j = SettingType::Json->value;
        $langs = ['en', 'de', 'lt', 'fr', 'es'];
        $ml = static fn (string $text) => json_encode(array_fill_keys($langs, $text), JSON_UNESCAPED_UNICODE);
        $count = 0;

        foreach ($map as $entry) {
            foreach (self::stringsFromFile(base_path('lang/en/'.$entry['file']), $entry['prefix']) as $key => $value) {
                $this->upsertUi($key, $value, $j, $ml);
                $count++;
            }
        }

        app(SettingsService::class)->forget('ui');

        return ['rows' => $count, 'groups' => 1];
    }

    /**
     * @param  array<int, array{file: string, prefix: string}>  $map
     */
    private static function keysFromMap(array $map): array
    {
        $keys = [];
        foreach ($map as $entry) {
            foreach (array_keys(self::stringsFromFile(base_path('lang/en/'.$entry['file']), $entry['prefix'])) as $k) {
                $keys[] = $k;
            }
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
