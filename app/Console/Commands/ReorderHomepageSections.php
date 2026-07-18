<?php

namespace App\Console\Commands;

use App\Models\Section;
use Illuminate\Console\Command;

class ReorderHomepageSections extends Command
{
    protected $signature = 'sections:reorder-homepage';
    protected $description = 'Reorder homepage sections according to the new design';

    public function handle()
    {
        $this->info('Reordering homepage sections...');

        // No two adjacent sections share a background shade (bg-ivory /
        // bg-ivory-alt / bg-paper / bg-ink); the 3 bg-ink sections
        // (part_inquiry, banner, contact_cta) sit spread apart rather than
        // clustered. Kept in sync with HomepageSectionsSeeder::SECTIONS'
        // sort_order and ReorderHomepageSectionsSeeder — update all three
        // together. Previously missing trust_bar and blog_preview entirely.
        $order = [
            'hero' => 10,
            'trust_bar' => 20,
            'how_it_works' => 30,
            'stats_counter' => 40,
            'popular_searches' => 50,
            'featured_brands' => 60,
            'part_inquiry' => 70,
            'testimonials' => 80,
            'shipping_info' => 90,
            'banner' => 100,
            'faqs' => 110,
            'contact_cta' => 120,
            'newsletter' => 130,
            'blog_preview' => 140,
        ];

        foreach ($order as $type => $sortOrder) {
            $updated = Section::where('type', $type)
                ->where('location', 'homepage')
                ->update(['sort_order' => $sortOrder]);

            if ($updated) {
                $this->line("✓ {$type} → position {$sortOrder}");
            }
        }

        // Clear section cache
        $cacheService = app(\App\Services\CacheService::class);
        $cacheService->forgetSections('homepage');

        $this->info('Homepage sections reordered successfully!');
        $this->warn('Remember to clear cache: php artisan cache:clear');

        return Command::SUCCESS;
    }
}
