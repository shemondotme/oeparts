<?php

namespace App\Services\Migration\Installer;

use App\Services\Migration\Import\ImportReportBuilder;
use App\Services\Migration\Import\ImportStateStore;
use App\Support\AtomicJsonFile;
use Illuminate\Support\Facades\Log;

/**
 * What the installer's report page shows about a finished import: counts, names of settings and plain sentences, never a value.
 *
 * The run's report.json (ImportReportBuilder) is built from the run's state, which never holds a setting value, a key, a passphrase
 * or a token. This class is a second wall: it copies fields BY NAME into a small view model, so a field that is ever added to the
 * report cannot reach this page by accident. The page is reached with a signed address that works for two hours.
 */
final class InstallerImportReport
{
    public function __construct(private readonly ImportReportBuilder $builder) {}

    /**
     * @return array<string, mixed>|null null when there is no report of an installer run with that id
     */
    public function forRun(string $id): ?array
    {
        if (! ImportStateStore::validId($id)) {
            return null;
        }

        $path = $this->builder->path($id);

        if (! is_file($path)) {
            return null;
        }

        try {
            $report = (new AtomicJsonFile($path))->read();
        } catch (\RuntimeException $e) {
            Log::channel('migration')->warning("The report of import {$id} could not be read for the installer: ".$e::class);

            return null;
        }

        if (! is_array($report) || (string) ($report['run']['door'] ?? '') !== 'installer') {
            return null;
        }

        $run = (array) ($report['run'] ?? []);
        $database = (array) ($report['database'] ?? []);
        $finishing = (array) ($report['finishing'] ?? []);
        $files = (array) ($report['files'] ?? []);
        $status = (string) ($report['outcome']['status'] ?? $run['status'] ?? '');

        return [
            'status' => $status,
            'complete' => $status === 'complete',
            'rolled_back' => $status === 'rolled_back',
            'changed_the_site' => ($run['changed_the_site'] ?? false) === true,
            'launch' => in_array($run['launch'] ?? null, ['live', 'staging'], true) ? $run['launch'] : null,
            'seconds' => is_numeric($run['seconds'] ?? null) ? (int) $run['seconds'] : null,
            'tables' => (int) ($database['tables_loaded'] ?? 0),
            'rows' => (int) ($database['rows_loaded'] ?? 0),
            'files_written' => (int) ($files['written'] ?? 0),
            'files_unchanged' => (int) ($files['unchanged'] ?? 0),
            'files_refused' => (int) array_sum(array_map('intval', (array) ($files['rejected'] ?? []))),
            'warnings' => $this->strings((array) ($report['warnings'] ?? [])),
            'reenter' => $this->strings((array) ($finishing['settings_to_enter_again'] ?? [])),
            'adjusted' => $this->pairs((array) ($finishing['adjusted_for_you'] ?? [])),
            'switched_off' => $this->strings((array) ($finishing['switched_off'] ?? [])),
            'error_code' => is_string($report['outcome']['error_code'] ?? null) ? $report['outcome']['error_code'] : null,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function strings(array $values): array
    {
        return array_values(array_filter(array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $values), static fn (string $v): bool => $v !== ''));
    }

    /**
     * Setting name => the plain note that says what was done to it (the notes carry no value; they are written by the engine).
     *
     * @param  array<int|string, mixed>  $values
     * @return array<string, string>
     */
    private function pairs(array $values): array
    {
        $pairs = [];

        foreach ($values as $name => $note) {
            if (is_string($name) && is_scalar($note)) {
                $pairs[$name] = (string) $note;
            }
        }

        return $pairs;
    }
}
