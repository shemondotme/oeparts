<?php

namespace App\Services\Migration\Import\Stages;

use App\Security\PackagePathPolicy;
use App\Services\Migration\Export\ExportDisks;
use App\Services\Migration\Groups\FileRoot;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\PackageIdentity;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageReader;

/**
 * Stage 2. Proves the WHOLE package is intact before anything on the site changes: every byte of the file against the
 * checksum in its trailer, every entry decrypted and hashed with the output thrown away (so no temporary disk is
 * needed), then that every table in it is one this application knows and one of the groups it says it carries, and how
 * many of its file paths the path policy will refuse.
 *
 * This is the "verify everything first" step a backup restore does not have. It reads only the package and writes only
 * the state file: not one database query changes anything. Time-boxed, with a cursor in the state, so a killed process
 * redoes at most one entry. The identity of the file is compared again on every call.
 *
 * File paths refused by the policy are counted, never fatal: the importer will skip exactly those files and the report
 * says how many. An unknown table, on the other hand, means the package was not made by this application: fatal.
 */
final class VerifyStage implements ImportStage
{
    /** Share of the stage's progress bar taken by the integrity pass (the rest is the registry and path checks). */
    private const INTEGRITY_SHARE = 0.9;

    public function __construct(
        private readonly Kdf $kdf,
        private readonly GroupRegistry $registry,
        private readonly PackagePathPolicy $policy,
    ) {}

    public function key(): string
    {
        return 'verify';
    }

    public function destructive(): bool
    {
        return false;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        $path = (string) ($state->package['path'] ?? '');
        PackageIdentity::assertUnchanged($path, (array) ($state->package['identity'] ?? []));

        $entry = $context->escrow->get(ImportContext::escrowId($state->id));

        if ($entry === null) {
            return ImportStepResult::needsPassphrase('The key for this package has expired: type the passphrase again.');
        }

        try {
            $reader = PackageReader::openWithKeys($path, $entry->keys, $this->kdf);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        }

        try {
            return $this->advance($state, $context, $reader);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        } finally {
            $reader->close();
        }
    }

    private function advance(ImportState $state, ImportContext $context, PackageReader $reader): ImportStepResult
    {
        $phase = (string) ($state->stageState['phase'] ?? 'integrity');

        if ($phase === 'integrity') {
            $budget = max(1, (int) config('migration.import.verify_budget_bytes', 64 * 1024 * 1024));
            $cursor = $state->stageState['cursor'] ?? null;

            do {
                $step = $reader->verifyStep(is_array($cursor) ? $cursor : null, $budget);
                $cursor = $step['cursor'];
            } while (! $step['done'] && ! $context->expired());

            if (! $step['done']) {
                $state->stageState['cursor'] = $cursor;

                return ImportStepResult::progress(self::INTEGRITY_SHARE * $this->integrityFraction($cursor, $reader), 'Checking every part of the package.');
            }

            $state->stageState = ['phase' => 'registry'];
            $phase = 'registry';
        }

        if ($phase === 'registry') {
            $this->checkTables($state, $reader);
            $state->stageState = ['phase' => 'paths', 'shard' => 0, 'rejected' => []];
            $phase = 'paths';
        }

        return $this->checkPaths($state, $context, $reader);
    }

    /** @param  array<string, mixed>  $cursor */
    private function integrityFraction(array $cursor, PackageReader $reader): float
    {
        if (($cursor['phase'] ?? '') === 'hash') {
            return 0.5 * min(1.0, ((int) ($cursor['offset'] ?? 0)) / max(1, $reader->fileSize()));
        }

        $entries = max(1, count($reader->entries()));

        return 0.5 + 0.5 * min(1.0, ((int) ($cursor['index'] ?? 0)) / $entries);
    }

    /** Every table in the package must be one this application has, belong to a group the package says it carries, and not be a system table. */
    private function checkTables(ImportState $state, PackageReader $reader): void
    {
        $groups = $this->groupsOf($reader);
        $allowed = array_flip($this->registry->tablesOf($groups));
        $tables = [];

        foreach ($reader->entries() as $entry) {
            if (($entry['kind'] ?? '') !== 'db') {
                continue;
            }

            $table = (string) ($entry['attrs']['table'] ?? '');

            if ($table === '' || ! isset($allowed[$table])) {
                throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'table '.($table === '' ? '(none)' : $table)]);
            }

            $tables[$table] = ($tables[$table] ?? 0) + (int) ($entry['rows'] ?? 0);
        }

        $state->package['tables'] = $tables;
        $state->package['groups'] = array_map(static fn (GroupKey $k): string => $k->value, $groups);
    }

    /** @return array<int, GroupKey> the groups the package says it carries (core is always one of them) */
    private function groupsOf(PackageReader $reader): array
    {
        $listed = (array) ($reader->meta()['export']['groups'] ?? []);
        $groups = [GroupKey::Core];

        foreach ($listed as $value) {
            $key = GroupKey::tryFrom((string) $value);

            if ($key === null) {
                throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'group '.$value]);
            }

            if ($key === GroupKey::System) {
                throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'group system']);
            }

            if (! in_array($key, $groups, true)) {
                $groups[] = $key;
            }
        }

        return $groups;
    }

    private function checkPaths(ImportState $state, ImportContext $context, PackageReader $reader): ImportStepResult
    {
        $shards = array_values(array_filter($reader->entries(), static fn (array $e): bool => ($e['kind'] ?? '') === 'files-index'));
        $roots = $this->allowedRoots($this->groupsOf($reader));
        $prefixes = array_map(static fn (array $d): string => $d['prefix'], ExportDisks::application());
        $shard = (int) ($state->stageState['shard'] ?? 0);
        $rejected = (array) ($state->stageState['rejected'] ?? []);

        while ($shard < count($shards)) {
            $this->scanShard($reader, (string) $shards[$shard]['name'], $roots, $prefixes, $rejected);
            $shard++;

            $state->stageState = ['phase' => 'paths', 'shard' => $shard, 'rejected' => $rejected];

            if ($shard < count($shards) && $context->expired()) {
                $fraction = self::INTEGRITY_SHARE + (1 - self::INTEGRITY_SHARE) * ($shard / max(1, count($shards)));

                return ImportStepResult::progress($fraction, 'Checking the file list.');
            }
        }

        $state->package['rejected_paths'] = $rejected;
        $state->package['files_in_index'] = array_sum(array_map(static fn (array $e): int => (int) ($e['rows'] ?? 0), $shards));
        $state->package['verified'] = true;
        $state->stageState = [];

        return ImportStepResult::done('Every part of the package is intact.');
    }

    /**
     * @param  array<int, string>  $roots
     * @param  array<string, string>  $prefixes  disk => root-relative prefix
     * @param  array<string, int>  $rejected  reason => count, added to
     */
    private function scanShard(PackageReader $reader, string $name, array $roots, array $prefixes, array &$rejected): void
    {
        $buffer = '';

        $judge = function (string $line) use ($roots, $prefixes, &$rejected): void {
            $row = json_decode($line, true);

            if (! is_array($row) || ! isset($row['d'], $row['p']) || ! is_string($row['p'])) {
                $rejected['bad_row'] = ($rejected['bad_row'] ?? 0) + 1;

                return;
            }

            if (! isset($prefixes[(string) $row['d']])) {
                $rejected['unknown_disk'] = ($rejected['unknown_disk'] ?? 0) + 1;

                return;
            }

            $reason = $this->policy->reject($prefixes[(string) $row['d']].'/'.ltrim($row['p'], '/'), $roots);

            if ($reason !== null) {
                $rejected[$reason->name] = ($rejected[$reason->name] ?? 0) + 1;
            }
        };

        $reader->readEntry($name, function (string $chunk) use (&$buffer, $judge): void {
            $buffer .= $chunk;

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);

                if ($line !== '') {
                    $judge($line);
                }
            }
        });

        if ($buffer !== '') {
            $judge($buffer);
        }
    }

    /**
     * @param  array<int, GroupKey>  $groups
     * @return array<int, string> root-relative folders a file of this package may land in
     */
    private function allowedRoots(array $groups): array
    {
        $prefixes = ExportDisks::application();
        $roots = [];

        foreach ($this->registry->fileRootsOf($groups) as $root) {
            /** @var FileRoot $root */
            $roots[] = $prefixes[$root->disk]['prefix'].'/'.$root->path;
        }

        return array_values(array_unique($roots));
    }
}
