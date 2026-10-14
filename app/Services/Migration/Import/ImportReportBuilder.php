<?php

namespace App\Services\Migration\Import;

use App\Support\AtomicJsonFile;
use Carbon\Carbon;

/**
 * The plain account of one import run: what was asked, what was checked, what was loaded and what needs a person's eye.
 *
 * It is built only from the run's own state, which never holds a setting VALUE, a passphrase, a key, the runner token or the
 * admin password hashes, and it copies fields by NAME (a whitelist), so a future field cannot leak into it by accident.
 * Counts and names only: how many rows each table received, which checks warned, how many files were refused and why.
 */
final class ImportReportBuilder
{
    public const VERSION = 1;

    public function __construct(private readonly ImportStateStore $store) {}

    /** @return array<string, mixed> */
    public function build(ImportState $state): array
    {
        $package = $state->package;
        $loaded = array_map('intval', (array) ($package['loaded'] ?? []));
        $audit = (array) ($package['audit'] ?? []);
        $files = (array) ($package['files'] ?? []);
        $preflight = (array) ($package['preflight'] ?? []);

        $report = [
            'report' => self::VERSION,
            'values_included' => false,
            'run' => [
                'id' => $state->id,
                'kind' => $state->kind,
                'status' => $state->status->value,
                'door' => (string) ($state->options['door'] ?? ''),
                'launch' => (string) ($state->options['launch'] ?? ''),
                'started_at' => $state->createdAt,
                'finished_at' => $state->finishedAt,
                'seconds' => $state->finishedAt === null ? null : (int) max(0, Carbon::parse($state->finishedAt)->diffInSeconds(Carbon::parse($state->createdAt), true)),
                'changed_the_site' => $state->isDestructive(),
                'initiated_by' => (string) ($state->initiator['email'] ?? ''),
            ],
            'package' => [
                'file' => isset($package['path']) ? basename((string) $package['path']) : null,
                'fingerprint' => $package['fingerprint'] ?? null,
                'groups' => array_values((array) ($package['groups'] ?? [])),
                'origin' => $package['origin'] ?? null,
            ],
            'checks' => $this->checks($preflight),
            'acknowledged' => array_values((array) ($state->options['acks'] ?? [])),
            'database' => [
                'tables_loaded' => count($loaded),
                'rows_loaded' => array_sum($loaded),
                'rows' => $loaded,
                'orphans' => (array) ($audit['orphans'] ?? []),
                'seeded_defaults' => (array) ($package['seeded'] ?? []),
                'indexes_dropped' => array_values((array) ($package['indexes_dropped'] ?? [])),
            ],
            'files' => $files === [] ? null : $files,
            'finishing' => $this->finishing($package),
            'stages_finished' => $this->finished($state),
            'outcome' => $this->outcome($state),
        ];

        $report['warnings'] = $this->warnings($report);

        return $report;
    }

    /** Write the report next to the run's state; returns its path. */
    public function write(ImportState $state): string
    {
        $file = new AtomicJsonFile($this->path($state->id));
        $file->write($this->build($state));

        return $file->path();
    }

    /** The status the written report says the run ended in, or null when there is no (readable) report. */
    public function statusOnDisk(string $runId): ?string
    {
        $path = $this->path($runId);

        if (! is_file($path)) {
            return null;
        }

        try {
            $report = (new AtomicJsonFile($path))->read();
        } catch (\RuntimeException) {
            return null;
        }

        return is_array($report) ? ($report['run']['status'] ?? null) : null;
    }

    public function path(string $runId): string
    {
        return $this->store->directory($runId).DIRECTORY_SEPARATOR.'report.json';
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @return array<int, array{key: string, label: string, status: string, message: string}>
     */
    private function checks(array $preflight): array
    {
        $checks = [];

        foreach ((array) ($preflight['checks'] ?? []) as $check) {
            $checks[] = [
                'key' => (string) ($check['key'] ?? ''),
                'label' => (string) ($check['label'] ?? ''),
                'status' => (string) ($check['status'] ?? ''),
                'message' => (string) ($check['message'] ?? ''),
            ];
        }

        return $checks;
    }

    /**
     * What the finishing stages did, by name and count only.
     *
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    private function finishing(array $package): array
    {
        $secrets = (array) ($package['secrets'] ?? []);
        $rewrite = (array) ($package['rewrite'] ?? []);
        $reset = (array) ($package['origin_reset'] ?? []);

        return [
            'secrets_written' => (int) ($secrets['written'] ?? 0),
            'settings_to_enter_again' => array_values(array_map('strval', array_merge((array) ($secrets['reenter'] ?? []), (array) ($secrets['not_found'] ?? [])))),
            'rewrite' => $rewrite === [] ? null : [
                'rows_changed' => (int) ($rewrite['rows_changed'] ?? 0), 'replacements' => (int) ($rewrite['replacements'] ?? 0),
                'invalid_json' => (int) ($rewrite['invalid_json'] ?? 0), 'skipped' => ($rewrite['skipped'] ?? false) === true,
            ],
            'adjusted_for_you' => array_map('strval', (array) ($package['normalized'] ?? [])),
            'emptied_tables' => array_map('intval', (array) ($reset['tables'] ?? [])),
            'switched_off' => array_values(array_map('strval', (array) ($reset['switched_off'] ?? []))),
            'counters_raised' => array_filter(array_map(static fn ($v): ?int => $v === null ? null : (int) $v, (array) ($package['sequences'] ?? [])), static fn (?int $v): bool => $v !== null),
            'sitemap' => $package['sitemap'] ?? null,
            'stage_warnings' => (array) ($package['stage_warnings'] ?? []),
        ];
    }

    /** @return array<int, string> the parts of the run that reported themselves finished, in the order they did */
    private function finished(ImportState $state): array
    {
        $done = [];

        foreach (array_keys(array_filter($state->flags)) as $flag) {
            if (str_ends_with((string) $flag, '.done') || str_starts_with((string) $flag, 'rollback.')) {
                $done[] = (string) $flag;
            }
        }

        return $done;
    }

    /** @return array<string, mixed> */
    private function outcome(ImportState $state): array
    {
        $error = $state->error;

        return [
            'status' => $state->status->value,
            'error_code' => is_array($error) ? ($error['code'] ?? null) : null,
            'error_stage' => is_array($error) ? ($error['stage'] ?? null) : null,
        ];
    }

    /**
     * What a person should look at, in plain words.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, string>
     */
    private function warnings(array $report): array
    {
        $warnings = [];

        foreach ($report['checks'] as $check) {
            if ($check['status'] === 'warn') {
                $warnings[] = $check['label'].': '.$check['message'];
            }
        }

        $orphans = (array) $report['database']['orphans'];

        if ($orphans !== []) {
            $warnings[] = count($orphans).' reference(s) between tables point at rows that are not there (the source site had them too).';
        }

        $files = (array) ($report['files'] ?? []);

        if ((int) array_sum((array) ($files['rejected'] ?? [])) > 0) {
            $warnings[] = array_sum((array) $files['rejected']).' file(s) were refused for safety and not copied.';
        }

        foreach ((array) ($report['finishing']['stage_warnings'] ?? []) as $messages) {
            foreach ((array) $messages as $message) {
                $warnings[] = (string) $message;
            }
        }

        foreach ((array) ($report['finishing']['settings_to_enter_again'] ?? []) as $name) {
            $warnings[] = 'Enter the setting '.$name.' again: it could not be carried over.';
        }

        if ($report['finishing']['switched_off'] !== []) {
            $warnings[] = count($report['finishing']['switched_off']).' automatic setting(s) were switched off on the new site; switch them on when you are ready.';
        }

        if ((int) ($files['leftovers'] ?? 0) > 0) {
            $warnings[] = (int) $files['leftovers'].' file(s) that were already on this site and are not in the package were left as they were.';
        }

        return $warnings;
    }
}
