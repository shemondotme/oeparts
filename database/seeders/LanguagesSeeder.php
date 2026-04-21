<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguagesSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            [
                'code'        => 'en',
                'name'        => 'English',
                'native_name' => 'English',
                'locale'      => 'en_GB',
                'flag_emoji'  => '🇬🇧',
                'is_active'   => true,
                'is_default'  => true,
                'sort_order'  => 1,
            ],
            [
                'code'        => 'de',
                'name'        => 'German',
                'native_name' => 'Deutsch',
                'locale'      => 'de_DE',
                'flag_emoji'  => '🇩🇪',
                'is_active'   => true,
                'is_default'  => false,
                'sort_order'  => 2,
            ],
            [
                'code'        => 'lt',
                'name'        => 'Lithuanian',
                'native_name' => 'Lietuvių',
                'locale'      => 'lt_LT',
                'flag_emoji'  => '🇱🇹',
                'is_active'   => true,
                'is_default'  => false,
                'sort_order'  => 3,
            ],
            [
                'code'        => 'fr',
                'name'        => 'French',
                'native_name' => 'Français',
                'locale'      => 'fr_FR',
                'flag_emoji'  => '🇫🇷',
                'is_active'   => true,
                'is_default'  => false,
                'sort_order'  => 4,
            ],
            [
                'code'        => 'es',
                'name'        => 'Spanish',
                'native_name' => 'Español',
                'locale'      => 'es_ES',
                'flag_emoji'  => '🇪🇸',
                'is_active'   => true,
                'is_default'  => false,
                'sort_order'  => 5,
            ],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['code' => $language['code']],
                $language
            );
        }
    }
}
