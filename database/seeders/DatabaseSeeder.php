<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            LanguagesSeeder::class,
            RolesSeeder::class,
            SequencesSeeder::class,
            CarriersSeeder::class,
            SectionsSeeder::class,
            DemoManufacturersAndPartsSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
