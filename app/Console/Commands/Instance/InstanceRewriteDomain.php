<?php

namespace App\Console\Commands\Instance;

use App\Models\Setting;
use App\Services\Instance\InstanceCacheReset;
use App\Services\Migration\Rewrite\DomainRewriter;
use App\Services\Migration\Rewrite\RewriteCursor;
use App\Services\Migration\Rewrite\RewritePlan;
use App\Services\SettingsService;
use App\Support\AtomicJsonFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves every stored address of the site from its old name to a new one, in the settings and in the content
 * tables the registry lists (see DomainRewriter for how it stays safe). Safe to stop and run again: it continues
 * where it stopped, and doing it twice changes nothing the second time.
 */
class InstanceRewriteDomain extends Command
{
    protected $signature = 'oeparts:instance:rewrite-domain
        {to : The new address of the site, for example https://shop.example}
        {--from=* : An old name the site was reached under (host or URL); repeat for several. Default: the stored site address}
        {--dry-run : Count what would change and write nothing}
        {--yes : Do it without asking}
        {--restart : Ignore an unfinished earlier run and start from the beginning}
        {--chunk=200 : Rows per step}
        {--json : Print one JSON document instead of text}';

    protected $description = 'Replace the old address of the site with a new one everywhere it is stored';

    public function handle(): int
    {
        $json = (bool) $this->option('json');

        try {
            $from = $this->oldNames();
            $plan = new RewritePlan($from, (string) $this->argument('to'));
        } catch (\InvalidArgumentException $e) {
            return $this->refuse($e->getMessage());
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('yes') && ! (! $json && $this->input->isInteractive() && $this->confirm('Rewrite '.implode(', ', $plan->from).' to '.$plan->to.' in the database? This changes stored content.'))) {
            return $this->refuse('Nothing was changed. Run with --dry-run to see what would change, or with --yes to do it.');
        }

        $directory = storage_path('app/instance');
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return $this->refuse("Cannot create {$directory}.");
        }

        $lock = fopen($directory.'/rewrite-domain.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->refuse('Another domain rewrite is running on this site.');
        }

        try {
            $result = $dryRun ? $this->preview($plan) : $this->apply($plan, $directory);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        $report = [
            'mode' => $dryRun ? 'dry-run' : 'applied', 'from' => $plan->from, 'to' => $plan->to,
            'rows_changed' => $result->total('rows_changed'), 'replacements' => $result->total('replacements'),
            'concurrent_skips' => $result->total('concurrent_skips'), 'invalid_json' => $result->total('invalid_json'),
            'steps' => $result->stats,
        ];

        if ($json) {
            $this->line((string) json_encode($report, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->summary($report);

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function oldNames(): array
    {
        $given = array_values(array_filter((array) $this->option('from'), static fn ($v): bool => trim((string) $v) !== ''));

        if ($given !== []) {
            return $given;
        }

        $stored = (string) Setting::where('group', 'general')->where('key', 'site_url')->value('value');

        if ($stored === '') {
            throw new \InvalidArgumentException('The site has no stored address, so there is nothing to look for. Pass the old name with --from=old.example.');
        }

        return [$stored];
    }

    private function preview(RewritePlan $plan): RewriteCursor
    {
        $this->say('Dry run: counting, writing nothing.');

        return $this->rewriter()->preview($plan);
    }

    private function apply(RewritePlan $plan, string $directory): RewriteCursor
    {
        $file = new AtomicJsonFile($directory.'/rewrite-domain.json');

        $cursor = $this->option('restart') ? RewriteCursor::fresh($plan) : RewriteCursor::load($file, $plan);

        if ($cursor->done) {
            $cursor = RewriteCursor::fresh($plan); // finished earlier: run again from the start (idempotent, finds nothing new)
        } elseif ($cursor->step > 0 || $cursor->lastId > 0) {
            $this->say('Continuing an earlier run at step '.($cursor->step + 1).'.');
        }

        $rewriter = $this->rewriter();
        $cursor->save($file);

        $steps = $rewriter->steps();

        while (! $cursor->done) {
            $before = $cursor->step;
            $rewriter->advance($plan, $cursor, 5.0, false, fn (RewriteCursor $c) => $c->save($file));

            if ($cursor->step !== $before && $cursor->step <= count($steps)) {
                $this->say('  '.($steps[$before] ?? '').' done');
            }
        }

        $this->forgetCaches();

        return $cursor;
    }

    private function rewriter(): DomainRewriter
    {
        return new DomainRewriter(chunkRows: max(1, (int) $this->option('chunk')));
    }

    /** Stored settings are cached for five minutes; the rewrite wrote around the cache, so it must be emptied. */
    private function forgetCaches(): void
    {
        try {
            $settings = app(SettingsService::class);

            foreach (DB::table('settings')->distinct()->pluck('group') as $group) {
                $settings->forget((string) $group);
            }

            app(InstanceCacheReset::class)->reset();
        } catch (\Throwable $e) {
            $this->warn('The rewrite is done, but the caches could not be cleared: '.$e->getMessage().' Run php artisan cache:clear.');
        }
    }

    /** @param  array<string, mixed>  $r */
    private function summary(array $r): void
    {
        $this->newLine();
        $this->line(($r['mode'] === 'dry-run' ? 'Would change' : 'Changed').': '.$r['rows_changed'].' row(s), '.$r['replacements'].' address(es)');
        $this->line('From: '.implode(', ', $r['from']).'  To: '.$r['to']);

        foreach ($r['steps'] as $step => $stats) {
            if (($stats['rows_changed'] ?? 0) > 0 || ($stats['invalid_json'] ?? 0) > 0 || ($stats['concurrent_skips'] ?? 0) > 0) {
                $this->line(sprintf('  %-22s %d row(s) changed%s%s', $step, $stats['rows_changed'] ?? 0,
                    ($stats['concurrent_skips'] ?? 0) > 0 ? ', '.$stats['concurrent_skips'].' edited meanwhile and skipped' : '',
                    ($stats['invalid_json'] ?? 0) > 0 ? ', '.$stats['invalid_json'].' JSON value(s) left (would have broken it)' : ''));
            }
        }

        if ($r['concurrent_skips'] > 0) {
            $this->warn('Some rows were edited while the rewrite ran and were skipped. Run the same command again to pick them up.');
        }

        if ($r['mode'] === 'applied') {
            $this->line('Next: php artisan oeparts:instance:scan-domain --host='.($r['from'][0] ?? '').' to see what still mentions the old name.');
        }
    }

    /** Progress text for a person; under --json standard output carries only the final document. */
    private function say(string $text): void
    {
        if (! $this->option('json')) {
            $this->line($text);
        }
    }

    private function refuse(string $message): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['error' => $message], JSON_UNESCAPED_SLASHES));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
