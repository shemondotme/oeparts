<?php

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $s = SettingType::String->value;
        $b = SettingType::Boolean->value;
        $i = SettingType::Integer->value;
        $j = SettingType::Json->value;

        $langs = ['en', 'de', 'lt', 'fr', 'es'];
        $ml = static fn (string $text) => json_encode(array_fill_keys($langs, $text));
        $pathList = [
            'en', 'de', 'lt', 'fr', 'es',
        ];

        $rows = [
            ['group' => 'preloader', 'key' => 'enabled', 'value' => '0', 'type' => $b],
            ['group' => 'preloader', 'key' => 'path_mode', 'value' => 'include', 'type' => $s],
            ['group' => 'preloader', 'key' => 'path_patterns', 'value' => json_encode($pathList), 'type' => $j],
            ['group' => 'preloader', 'key' => 'min_display_ms', 'value' => '450', 'type' => $i],
            ['group' => 'preloader', 'key' => 'max_display_ms', 'value' => '6000', 'type' => $i],
            ['group' => 'preloader', 'key' => 'headline', 'value' => $ml('OEM·HUB.'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'spec_line', 'value' => $ml('§ SYS · INIT / EU'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'subline', 'value' => $ml('Genuine Parts Index'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'status_line', 'value' => $ml('Calibrating Index'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'foot_left', 'value' => $ml('OEMHUB · EU'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'foot_right', 'value' => $ml('LIVE CATALOGUE'), 'type' => $j],
            ['group' => 'preloader', 'key' => 'aria_label', 'value' => $ml('Loading'), 'type' => $j],

            ['group' => 'ui', 'key' => 'hero_index_badge', 'value' => $ml('§ INDEX'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_live_status', 'value' => $ml('CATALOGUE LIVE'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_eyebrow', 'value' => $ml('Genuine OEM Parts Index · 1,000,000+'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_subtext_default', 'value' => $ml('Enter any OEM number. We return matches, cross-references, and verified suppliers across the European Union — or open a concierge inquiry if the part is rare.'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_title', 'value' => $ml('Specification'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_source_label', 'value' => $ml('Source'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_source_badge', 'value' => $ml('VERIFIED · EU'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_search_strip', 'value' => $ml('§ ENTER OEM NUMBER'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_search_meta_hint', 'value' => $ml('min :min chars · uppercase alphanumeric'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_indexed_label', 'value' => $ml('Indexed:'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_footer_pill_1', 'value' => $ml('Verified Suppliers'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_footer_pill_2', 'value' => $ml('TLS 1.3 · SSL'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_footer_pill_3', 'value' => $ml('27 EU Countries'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r1_label', 'value' => $ml('Catalogue'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r2_label', 'value' => $ml('Manufacturers'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r2_value', 'value' => $ml('214'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r3_label', 'value' => $ml('Cross-refs'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r3_value', 'value' => $ml('3.2M'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r4_label', 'value' => $ml('Avg. despatch'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r4_value', 'value' => $ml('24h'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r5_label', 'value' => $ml('Languages'), 'type' => $j],
            ['group' => 'ui', 'key' => 'hero_spec_r5_value', 'value' => $ml('EN·DE·LT·FR·ES'), 'type' => $j],
        ];

        foreach ($rows as $row) {
            Setting::updateOrCreate(
                ['group' => $row['group'], 'key' => $row['key']],
                [
                    'value' => $row['value'],
                    'type' => $row['type'],
                    'is_encrypted' => false,
                ]
            );
        }
    }

    public function down(): void
    {
        $keys = [
            'preloader' => [
                'enabled', 'path_mode', 'path_patterns', 'min_display_ms', 'max_display_ms',
                'headline', 'spec_line', 'subline', 'status_line', 'foot_left', 'foot_right', 'aria_label',
            ],
            'ui' => [
                'hero_index_badge', 'hero_live_status', 'hero_eyebrow', 'hero_subtext_default', 'hero_spec_title',
                'hero_source_label', 'hero_source_badge', 'hero_search_strip', 'hero_search_meta_hint', 'hero_indexed_label',
                'hero_footer_pill_1', 'hero_footer_pill_2', 'hero_footer_pill_3',
                'hero_spec_r1_label', 'hero_spec_r2_label', 'hero_spec_r2_value', 'hero_spec_r3_label', 'hero_spec_r3_value',
                'hero_spec_r4_label', 'hero_spec_r4_value', 'hero_spec_r5_label', 'hero_spec_r5_value',
            ],
        ];
        foreach ($keys as $group => $klist) {
            foreach ($klist as $k) {
                Setting::where('group', $group)->where('key', $k)->delete();
            }
        }
    }
};
