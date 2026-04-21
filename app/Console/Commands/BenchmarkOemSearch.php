<?php

namespace App\Console\Commands;

use App\Services\OemNormalizerService;
use App\Services\SearchService;
use Illuminate\Console\Command;

/**
 * Measure average SearchService::search() time (DB + aggregations, no HTTP/view).
 * PRD target: OEM search under 200ms; this helps compare environments.
 */
class BenchmarkOemSearch extends Command
{
    protected $signature = 'oem:benchmark
                            {oem? : Raw OEM string to normalize and search}
                            {--iterations=25 : Number of timed iterations}
                            {--paginate : Use paginated search like the web (slower)}';

    protected $description = 'Benchmark OEM search service (avg ms per iteration)';

    public function handle(SearchService $search, OemNormalizerService $normalizer): int
    {
        $raw = (string) ($this->argument('oem') ?? '1K0407271E');
        $normalized = $normalizer->normalize($raw);
        $n = max(1, (int) $this->option('iterations'));
        $paginate = (bool) $this->option('paginate');

        $t = microtime(true);
        for ($i = 0; $i < $n; $i++) {
            $search->search($raw, null, null, [
                'limit'        => 100,
                'paginate'     => $paginate,
                'per_page'     => 20,
                'lang'         => 'en',
                'sort'         => 'default',
                'condition'    => null,
                'in_stock_only' => false,
            ]);
        }
        $avgMs = (microtime(true) - $t) * 1000 / $n;

        $this->info('Database driver: ' . config('database.default'));
        $this->line('Normalized OEM: ' . $normalized);
        $this->line('Iterations: ' . $n . ($paginate ? ' (paginate=on)' : ' (paginate=off)'));
        $this->info('Avg SearchService::search(): ' . round($avgMs, 2) . ' ms');

        return self::SUCCESS;
    }
}
