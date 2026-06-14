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
            // DashboardDemoSeeder::class, // Run manually to seed rich metrics/charts demo data for the admin dashboard: php artisan db:seed --class=DashboardDemoSeeder
            CmsFooterPagesSeeder::class,
            TestimonialsAndFaqsSeeder::class,
        ]);
    }
}
