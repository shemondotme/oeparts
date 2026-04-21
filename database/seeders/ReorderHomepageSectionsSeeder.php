<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class ReorderHomepageSectionsSeeder extends Seeder
{
    public function run(): void
    {
        // Swapped positions 2 and 14 (Stats Counter ↔ Trust Bar)
        $desiredOrder = [
            'hero',              // Navy - Above fold
            'trust_bar',         // Amber - Trust signals at top
            'how_it_works',      // White - Explain process
            'featured_brands',   // White/Amber - Show brands
            'popular_searches',  // White/Orange - Quick access
            'banner',            // Navy - Pattern break, highlight
            'part_inquiry',      // Amber - Lead capture
            'testimonials',      // White/Amber - Reviews
            'shipping_info',     // White/Blue - Delivery trust
            'blog_preview',      // Light blue - Content
            'faqs',              // White/Blue - Address concerns
            'contact_cta',       // Navy - Final conversion push
            'newsletter',        // Amber - Email capture
            'stats_counter',     // Off-white - Stats at bottom
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
