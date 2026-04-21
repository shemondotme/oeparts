<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

/**
 * Fix section sort_order to match the intended homepage order.
 *
 * Intended order based on actual page design:
 * 1.  Hero            — Main search & hero content
 * 2.  Stats Counter   — Numbers showcase (right after hero)
 * 3.  How It Works    — 3-step process
 * 4.  Featured Brands — Brand showcase
 * 5.  Popular Searches — Trending OEM numbers
 * 6.  Part Inquiry    — Can't find your part form
 * 7.  Banner          — Promotional banner
 * 8.  Testimonials    — Customer reviews
 * 9.  Shipping Info   — EU delivery info
 * 10. Blog Preview    — Latest blog posts
 * 11. FAQs            — Frequently asked questions
 * 12. Contact CTA     — Dark contact section
 * 13. Newsletter      — Email subscription
 * 14. Trust Bar       — Trust badges before footer
 */
class FixSectionOrderSeeder extends Seeder
{
    private const ORDERED_TYPES = [
        'hero',
        'stats_counter',
        'how_it_works',
        'featured_brands',
        'popular_searches',
        'part_inquiry',
        'banner',
        'testimonials',
        'shipping_info',
        'blog_preview',
        'faqs',
        'contact_cta',
        'newsletter',
        'trust_bar',
    ];

    public function run(): void
    {
        $this->command->info("Fixing section sort_order...\n");

        foreach (self::ORDERED_TYPES as $index => $type) {
            $sortOrder = ($index + 1) * 10; // 10, 20, 30, etc.

            $updated = Section::where('type', $type)
                ->where('location', 'homepage')
                ->update(['sort_order' => $sortOrder]);

            $status = $updated > 0 ? '✓' : '✗';
            $this->command->line("  {$status} {$type} → sort_order {$sortOrder}");
        }

        // Verify the order
        $this->command->info("\nCurrent section order (active sections):");
        $sections = Section::where('location', 'homepage')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['type', 'sort_order', 'is_active']);

        foreach ($sections as $i => $section) {
            $this->command->line("  " . ($i + 1) . ". {$section->type} (sort_order: {$section->sort_order})");
        }

        $this->command->info("\n✅ Section order fixed successfully!");
    }
}
