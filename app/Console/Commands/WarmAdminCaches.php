<?php

namespace App\Console\Commands;

use App\Filament\Pages\Settings\SeoHealthDashboard;
use App\Models\Manufacturer;
use Illuminate\Console\Command;

/**
 * Fills the caches behind the admin pages that count a whole catalogue (SEO Health, the Manufacturers list), so that
 * opening them is never the moment the counting happens. After an update or "Clear Application Cache" those keys are
 * gone and the first visit used to wait for the counting — about two minutes on a million-product catalogue.
 *
 * The old figures are replaced only when the new ones exist, so a visitor never meets an empty cache while this runs.
 */
class WarmAdminCaches extends Command
{
    protected $signature = 'oeparts:cache:warm-admin';

    protected $description = 'Pre-compute the catalogue-wide figures of the SEO Health and Manufacturers pages';

    public function handle(): int
    {
        $started = microtime(true);

        try {
            Manufacturer::warmProductCounts();
            app(SeoHealthDashboard::class)->warmCaches();
        } catch (\Throwable $e) {
            $this->error('Warm-up failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Admin caches warmed in %.1f s.', microtime(true) - $started));

        return self::SUCCESS;
    }
}
