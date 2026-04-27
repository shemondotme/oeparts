<?php

namespace Tests\Unit;

use App\Enums\SettingType;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UiCopyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ui_copy_falls_back_to_lang_file_when_empty(): void
    {
        $this->assertStringContainsString('Genuine', ui_copy('cart_genuine_x_missing', 'cart.genuine_part'));
    }

    #[Test]
    public function ui_copy_uses_setting_when_set(): void
    {
        $langs = ['en', 'de', 'lt', 'fr', 'es'];
        Setting::updateOrCreate(
            ['group' => 'ui', 'key' => 'search_test_unique'],
            [
                'value' => json_encode(array_fill_keys($langs, 'AAA'), JSON_UNESCAPED_UNICODE),
                'type' => SettingType::Json->value,
                'is_encrypted' => false,
            ]
        );
        app(SettingsService::class)->forget('ui');

        $this->assertSame('AAA', ui_copy('search_test_unique', 'search.unknown_brand'));
    }

    #[Test]
    public function ui_copy_replaces_placeholders(): void
    {
        $langs = ['en', 'de', 'lt', 'fr', 'es'];
        Setting::updateOrCreate(
            ['group' => 'ui', 'key' => 'search_incl_vat_test'],
            [
                'value' => json_encode(array_fill_keys($langs, 'Incl. :rate% VAT')),
                'type' => SettingType::Json->value,
                'is_encrypted' => false,
            ]
        );
        app(SettingsService::class)->forget('ui');

        $this->assertStringContainsString('21', ui_copy('search_incl_vat_test', 'search.incl_vat', ['rate' => '21']));
    }
}
