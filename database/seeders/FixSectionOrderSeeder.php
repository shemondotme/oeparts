<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

/**
 * Fix section sort_order to match the intended homepage order.
 *
 * The 3 dark (bg-ink) sections — part_inquiry, banner, contact_cta —
 * are spaced at positions 4, 8, 12 so they are NEVER consecutive.
 * Visual rhythm: 3 light → dark → 3 light → dark → 3 light → dark → 2 light
 *
 * Intended order:
 *  1. hero          (light)
 *  2. trust_bar     (light)
 *  3. stats_counter (light)
 *  4. part_inquiry  🔵 DARK
 *  5. how_it_works  (light)
 *  6. featured_brands (light)
 *  7. popular_searches (light)
 *  8. banner        🔵 DARK
 *  9. testimonials  (light)
 * 10. shipping_info (light)
 * 11. faqs          (light)
 * 12. contact_cta   🔵 DARK
 * 13. newsletter    (light)
 * 14. blog_preview  (light)
 */
class FixSectionOrderSeeder extends Seeder
{
    private const ORDERED_TYPES = [
        'hero',
        'trust_bar',
        'stats_counter',
        'part_inquiry',
        'how_it_works',
        'featured_brands',
        'popular_searches',
        'banner',
        'testimonials',
        'shipping_info',
        'faqs',
        'contact_cta',
        'newsletter',
        'blog_preview',
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
