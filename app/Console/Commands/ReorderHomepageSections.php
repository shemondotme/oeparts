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

        // New section order
        $order = [
            'hero' => 1,
            'stats_counter' => 2,
            'how_it_works' => 3,
            'featured_brands' => 4,
            'popular_searches' => 5,
            'part_inquiry' => 6,
            'testimonials' => 7,
            'shipping_info' => 8,
            'faqs' => 9,
            'contact_cta' => 10,
            'newsletter' => 11,
            'banner' => 12,
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
