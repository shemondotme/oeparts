<?php

namespace App\Console\Commands\Instance;

use App\Services\Migration\Rewrite\DomainLeakScanner;
use App\Services\Migration\Rewrite\RewritePlan;
use Illuminate\Console\Command;

/**
 * Where does the old address of the site still appear? Read only. Files every place that still mentions it under
 * what the rewrite decided about that place, and says plainly when a column could not be checked in time.
 */
class InstanceScanDomain extends Command
{
    protected $signature = 'oeparts:instance:scan-domain
        {--host=* : An old name to look for (host or URL); repeat for several. Default: the stored site address}
        {--strict : Exit with an error when a gap or an unchecked column is found}
        {--json : Print one JSON document instead of text}';

    protected $description = 'List where the old address of the site is still mentioned';

    public function handle(DomainLeakScanner $scanner): int
    {
        $given = array_values(array_filter((array) $this->option('host'), static fn ($v): bool => trim((string) $v) !== ''));
        $hosts = $given;

        if ($hosts === []) {
            $hosts = $scanner->suggestLegacyHosts();

            if ($hosts === []) {
                $this->error('There is no address to look for. Pass one with --host=old.example.');

                return self::FAILURE;
            }
        }

        try {
            // Reuses the rewrite plan's validation and normalisation of names; the target is irrelevant to a scan.
            $normalised = (new RewritePlan($hosts, 'https://placeholder.invalid'))->from;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $report = $scanner->scan($normalised);
        $suggest = $scanner->suggestLegacyHosts($normalised);

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray() + ['suggested_other_names' => $suggest], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $this->option('strict') && $report->needsAttention() ? self::FAILURE : self::SUCCESS;
        }

        $this->line('Looking for: '.implode(', ', $normalised));

        $labels = [
            DomainLeakScanner::LEAK => 'GAPS (nobody decided what to do here)',
            DomainLeakScanner::REWRITTEN => 'Rewritten places (what remains is a mention the rewrite left on purpose)',
            DomainLeakScanner::SCAN_ONLY => 'Scripts and instructions (reported, never edited: check them yourself)',
            DomainLeakScanner::MAILBOX => 'Email addresses of the old domain (never rewritten)',
            DomainLeakScanner::PRESERVED => 'History (kept as it was written)',
        ];

        foreach ($labels as $class => $label) {
            $entries = $report->ofClass($class);

            if ($entries === []) {
                continue;
            }

            $this->newLine();
            $this->line($label.':');

            foreach ($entries as $e) {
                $this->line(sprintf('  %-40s %s%s', $e['table'].'.'.$e['column'], $e['incomplete'] ? 'could not be checked in time' : $e['rows'].' row(s)', ''));
            }
        }

        foreach ($report->incomplete() as $e) {
            $this->warn("{$e['table']}.{$e['column']} ran out of time: it may still mention the old name.");
        }

        if ($report->entries === []) {
            $this->info('The old name appears nowhere.');
        }

        if ($suggest !== []) {
            $this->newLine();
            $this->line('The site may also have been reached as: '.implode(', ', $suggest).' (pass them with --host / --from).');
        }

        return $this->option('strict') && $report->needsAttention() ? self::FAILURE : self::SUCCESS;
    }
}
