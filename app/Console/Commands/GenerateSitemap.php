<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate XML sitemaps for all public content';

    public function handle(SitemapService $sitemapService): int
    {
        $this->info('Generating sitemaps...');

        try {
            $files = $sitemapService->generateAll();

            $this->info('Sitemaps generated successfully:');
            foreach ($files as $file) {
                $this->line("  - {$file}");
            }

            $this->info('Sitemap index available at: ' . $sitemapService->getSitemapUrl());

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to generate sitemaps: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}