<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate XML sitemaps for all public content';

    /**
     * Execute the console command.
     */
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