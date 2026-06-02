<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class ReorderHomepageSectionsSeeder extends Seeder
{
    public function run(): void
    {
        // 3 light → DARK → 3 light → DARK → 3 light → DARK → 2 light
        // Dark (bg-ink) sections at positions 4, 8, 12 — never consecutive
        $desiredOrder = [
            'hero',              // Light - Above fold search
            'trust_bar',         // Light - Trust signals
            'stats_counter',     // Light - Credibility numbers
            'part_inquiry',      // 🔵 DARK - Can't find part?
            'how_it_works',      // Light - Process
            'featured_brands',   // Light - Brand showcase
            'popular_searches',  // Light - Trending numbers
            'banner',            // 🔵 DARK - Workshop promo
            'testimonials',      // Light - Reviews
            'shipping_info',     // Light - EU delivery
            'faqs',              // Light - Objections
            'contact_cta',       // 🔵 DARK - Final contact
            'newsletter',        // Light - Email capture
            'blog_preview',      // Light - Content footer
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
