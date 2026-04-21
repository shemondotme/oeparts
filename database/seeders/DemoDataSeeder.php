<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo catalog data (manufacturers, products, logos).
 * Implementation lives in {@see DemoManufacturersAndPartsSeeder}.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(DemoManufacturersAndPartsSeeder::class);
    }
}
