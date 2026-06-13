<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ConditionSeeder::class,
            SettingsSeeder::class,
            LanguagesSeeder::class,
            RolesSeeder::class,
            AdminSeeder::class,
            SequencesSeeder::class,
            CarriersSeeder::class,
            SectionsSeeder::class,
            ShippingZonesAndMethodsSeeder::class,
            DemoDataSeeder::class,
            CmsFooterPagesSeeder::class,
            TestimonialsAndFaqsSeeder::class,
        ]);
    }
}
