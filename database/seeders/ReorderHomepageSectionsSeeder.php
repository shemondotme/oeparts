<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class ReorderHomepageSectionsSeeder extends Seeder
{
    public function run(): void
    {
        // No two adjacent sections share a background shade (bg-ivory /
        // bg-ivory-alt / bg-paper / bg-ink). Dark (bg-ink) sections at
        // positions 7, 10, 12 — spread apart, never consecutive. Kept in
        // sync with HomepageSectionsSeeder::SECTIONS' sort_order — update
        // both together.
        $desiredOrder = [
            'hero',              // ivory      - Above fold search
            'trust_bar',         // ivory-alt  - Trust signals
            'how_it_works',      // paper      - Process
            'stats_counter',     // ivory      - Credibility numbers
            'popular_searches',  // paper      - Trending numbers (live search data)
            'featured_brands',   // ivory      - Brand showcase
            'part_inquiry',      // 🔵 ink     - Can't find part?
            'testimonials',      // paper      - Reviews
            'shipping_info',     // ivory      - EU delivery
            'banner',            // 🔵 ink     - Workshop promo
            'faqs',              // ivory      - Objections
            'contact_cta',       // 🔵 ink     - Direct contact
            'newsletter',        // paper      - Email capture
            'blog_preview',      // ivory      - Content footer
        ];

        // Get all active homepage sections
        $sections = Section::where('location', 'homepage')
            ->where('is_active', true)
            ->get()
            ->keyBy('type');

        foreach ($desiredOrder as $index => $type) {
            if ($sections->has($type)) {
                $section = $sections->get($type);
                $section->update(['sort_order' => $index + 1]);
                $this->command->info("✓ {$type} => order " . ($index + 1));
            } else {
                $this->command->warn("⚠ {$type} not found (skipping)");
            }
        }

        $this->command->info("\n✓ Homepage sections reordered!");
    }
}
