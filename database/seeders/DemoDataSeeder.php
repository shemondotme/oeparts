<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo catalog data (manufacturers, products, logos).
 * Implementation lives in {@see DemoManufacturersAndPartsSeeder}.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoManufacturersAndPartsSeeder::class,
            BlogPostsSeeder::class,
        ]);
    }
}
