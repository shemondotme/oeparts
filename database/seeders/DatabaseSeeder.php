<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo-data seeders at a glance (four names, easy to conflate on a fresh checkout):
 * - DemoDataSeeder: orchestrator run below — calls DemoManufacturersAndPartsSeeder
 *   + BlogPostsSeeder (catalog: manufacturers, products, logos, blog posts).
 * - DemoManufacturersAndPartsSeeder: the actual catalog data, called by the above —
 *   not meant to be run standalone.
 * - DashboardDemoSeeder: a separate, heavier seeder (demo orders, carts, coupons,
 *   contact messages, refunds, search logs) for populating admin dashboard
 *   charts/metrics with something to look at. Deliberately NOT run below —
 *   run it manually: php artisan db:seed --class=DashboardDemoSeeder
 * - App\Console\Commands\DemoSetup ('demo:setup' artisan command): a separate
 *   fresh-install bootstrapper with its own hardcoded core-seeder list (mirrors,
 *   but does not call, this file) — it only calls DemoDataSeeder, and only when
 *   --seed/--yes is passed.
 */
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
