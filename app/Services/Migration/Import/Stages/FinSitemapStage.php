<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Stage 24 (a failure is a warning). The sitemap is not in the package. A small catalog gets its sitemap built right now; a big
 * one (over `migration.import.sitemap_inline_max` products) is left to the nightly schedule, because building it would keep the
 * site closed for minutes for something search engines will not ask for tonight.
 */
final class FinSitemapStage extends FinalisingStage
{
    public function key(): string
    {
        return 'fin_sitemap';
    }

    protected function after(): string
    {
        return 'fin.links.done';
    }

    protected function flag(): string
    {
        return 'fin.sitemap.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The sitemap is handled.');
        }

        $this->guard($state);

        $products = (int) DB::table('products')->count();

        if ($products > (int) config('migration.import.sitemap_inline_max', 50_000)) {
            $state->package['sitemap'] = 'scheduled';
            $this->warn($state, 'The sitemap will be built by the nightly schedule (the catalog is large).');
        } else {
            try {
                Artisan::call('sitemap:generate');
                $state->package['sitemap'] = 'built';
            } catch (\Throwable $e) {
                $state->package['sitemap'] = 'failed';
                $this->warn($state, 'The sitemap could not be built now: '.$e->getMessage().' The nightly schedule will build it.');
            }
        }

        $state->setFlag($this->flag());

        return ImportStepResult::done('The sitemap is handled.');
    }
}
