<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OptimizeProductSearchIndex extends Command
{
    protected $signature = 'oeparts:products:optimize-search-index';

    protected $description = "Resync the products table's MySQL FULLTEXT (ngram) index against normalized_oem — InnoDB buffers FULLTEXT updates internally and can leave the on-disk index stale (existing products silently unsearchable by OEM number) until an OPTIMIZE TABLE forces a flush";

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->info('Not running on MySQL — nothing to optimize.');

            return self::SUCCESS;
        }

        $started = microtime(true);

        DB::statement('OPTIMIZE TABLE products');

        $seconds = round(microtime(true) - $started, 1);

        $this->info("Optimized products table's search index in {$seconds}s.");
        Log::info('Product search index optimized', ['seconds' => $seconds]);

        return self::SUCCESS;
    }
}
