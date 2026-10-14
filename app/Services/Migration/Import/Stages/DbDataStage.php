<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\RowDecoder;
use App\Services\Migration\Import\RowLoader;
use App\Services\Migration\Import\SelectedGroups;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageReader;
use Illuminate\Support\Facades\DB;

/**
 * Stage 9. Loads the package's rows, table by table, parents before the rows that point at them, in batches that are
 * committed one at a time.
 *
 * Resume without duplicates. The checkpoint holds the table, the segment, how many rows of that segment are in, and how
 * many rows the table must hold. A new process counts the table before it does anything:
 *   - the count is what the checkpoint says: carry on from there;
 *   - the count is what the checkpoint says PLUS the next batch: that batch was inserted just before the process died,
 *     so it is skipped (an INSERT is all or nothing, and the batches are cut the same way every time);
 *   - anything else: a load was cut off in a way that cannot be reasoned about, so that table is emptied and loaded again.
 * Nothing else may write to these tables meanwhile, which is why the queue workers are held.
 *
 * Only tables of the groups being imported are loaded; the others stay empty until their defaults come back. Every row,
 * cell and column was already proven to belong to this application and this schema (verify, and the column check at the
 * start of every segment).
 */
final class DbDataStage implements ImportStage
{
    public function __construct(
        private readonly Kdf $kdf,
        private readonly GroupRegistry $registry,
        private readonly RowLoader $loader,
        private readonly ImportMarker $marker,
        private readonly ImportStateStore $store,
    ) {}

    public function key(): string
    {
        return 'db_data';
    }

    public function destructive(): bool
    {
        return true;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('data.done')) {
            return ImportStepResult::done('The data is loaded.');
        }

        if (! $this->marker->isActive() || ! $state->flag('clear.done')) {
            throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => 'db_data', 'why' => 'the old data has not been cleared']);
        }

        $keys = $context->escrow->get(ImportContext::escrowId($state->id));

        if ($keys === null) {
            return ImportStepResult::needsPassphrase('The key for this package has expired: type the passphrase again.');
        }

        try {
            $reader = PackageReader::openWithKeys((string) $state->package['path'], $keys->keys, $this->kdf);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        }

        try {
            return $this->load($state, $context, $reader);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        } finally {
            $reader->close();
        }
    }

    private function load(ImportState $state, ImportContext $context, PackageReader $reader): ImportStepResult
    {
        $this->loader->prepareSession();

        $plan = $this->plan($state, $reader);
        $tables = array_keys($plan);
        $cursor = (array) ($state->stageState['cur'] ?? ['ti' => 0, 'pi' => 0, 'done' => 0, 'expected' => 0]);
        $totalRows = max(1, array_sum(array_map(static fn (array $parts): int => array_sum(array_column($parts, 'rows')), $plan)));

        while ($cursor['ti'] < count($tables)) {
            $table = $tables[$cursor['ti']];
            $parts = $plan[$table];

            while ($cursor['pi'] < count($parts)) {
                $outcome = $this->loadPart($state, $context, $reader, $table, $parts[$cursor['pi']], $cursor);

                if ($outcome === 'restart') {
                    $this->loader->truncate($table);
                    $cursor = ['ti' => $cursor['ti'], 'pi' => 0, 'done' => 0, 'expected' => 0];
                    $this->checkpoint($state, $cursor);

                    continue;
                }

                if ($outcome === 'stopped') {
                    return ImportStepResult::progress($this->rowsLoaded($plan, $cursor) / $totalRows, "Loading {$table}.");
                }

                $cursor['pi']++;
                $cursor['done'] = 0;
                $this->checkpoint($state, $cursor);
            }

            $expected = array_sum(array_column($parts, 'rows'));

            if ((int) DB::table($table)->count() !== $expected) {
                throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => 'db_data', 'why' => "{$table} holds a different number of rows than the package"]);
            }

            $cursor = ['ti' => $cursor['ti'] + 1, 'pi' => 0, 'done' => 0, 'expected' => 0];
            $this->checkpoint($state, $cursor);

            if ($cursor['ti'] < count($tables) && $context->expired()) {
                return ImportStepResult::progress($this->rowsLoaded($plan, $cursor) / $totalRows, "Loaded {$table}.");
            }
        }

        $state->package['loaded'] = array_map(static fn (array $parts): int => array_sum(array_column($parts, 'rows')), $plan);
        $state->setFlag('data.done');
        $state->stageState = [];

        return ImportStepResult::done('The data is loaded.');
    }

    /**
     * Load one segment from where the checkpoint says. 'done' = the whole segment is in; 'stopped' = time ran out between
     * batches (the checkpoint is saved); 'restart' = the table must be emptied and loaded again.
     *
     * @param  array<string, mixed>  $part  a table-of-contents record of the segment
     * @param  array<string, int>  $cursor  updated as batches are committed
     */
    private function loadPart(ImportState $state, ImportContext $context, PackageReader $reader, string $table, array $part, array &$cursor): string
    {
        $startCount = (int) DB::table($table)->count();

        // A table that is not clean when its first segment starts was not emptied: that cannot be trusted.
        if ($cursor['pi'] === 0 && $cursor['done'] === 0 && $startCount !== 0) {
            return 'restart';
        }

        $segment = new SegmentLoader($this->loader, $table, $startCount, $cursor, function (array $moved) use ($state, &$cursor): void {
            $cursor = $moved;
            $this->checkpoint($state, $cursor);
        }, $context);

        try {
            $reader->readEntry((string) $part['name'], static function (string $chunk) use ($segment): void {
                $segment->feed($chunk);
            });

            $segment->finish();
        } catch (RestartTable) {
            return 'restart';
        } catch (StopLoading) {
            return 'stopped';
        }

        if ($segment->rowsSeen() !== (int) $part['rows']) {
            throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => "rows of {$part['name']}"]);
        }

        return 'done';
    }

    /**
     * The tables to load, in the order to load them (parents first), each with its segments in order.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function plan(ImportState $state, PackageReader $reader): array
    {
        $groups = SelectedGroups::of($state);
        $byTable = [];

        foreach ($reader->entries() as $entry) {
            if (($entry['kind'] ?? '') !== 'db') {
                continue;
            }

            $table = (string) ($entry['attrs']['table'] ?? '');

            if ($this->wanted($table, $groups)) {
                $byTable[$table][] = ['name' => (string) $entry['name'], 'rows' => (int) ($entry['rows'] ?? 0)];
            }
        }

        foreach ($byTable as $table => $parts) {
            usort($parts, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
            $byTable[$table] = $parts;
        }

        $ordered = [];
        $order = (new DependencyResolver($this->registry, new FkGraph))->resolve($groups)->importOrder();

        foreach ($order as $table) {
            if (isset($byTable[$table])) {
                $ordered[$table] = $byTable[$table];
            }
        }

        foreach ($byTable as $table => $parts) {
            $ordered[$table] ??= $parts; // a table the resolver does not list still loads, after the rest
        }

        return $ordered;
    }

    /** @param  array<int, GroupKey>  $groups */
    private function wanted(string $table, array $groups): bool
    {
        foreach ($this->registry->ownersOf($table) as $owner) {
            if (in_array($owner, $groups, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, array<int, array<string, mixed>>>  $plan */
    private function rowsLoaded(array $plan, array $cursor): int
    {
        $rows = 0;
        $tables = array_values($plan);

        for ($t = 0; $t < min($cursor['ti'], count($tables)); $t++) {
            $rows += array_sum(array_column($tables[$t], 'rows'));
        }

        return $rows + (int) $cursor['expected'];
    }

    /** @param  array<string, int>  $cursor */
    private function checkpoint(ImportState $state, array $cursor): void
    {
        $state->stageState['cur'] = $cursor;
        $state->touch();
        $this->store->save($state);
    }
}

/** Thrown inside the row sink to unwind out of the package reader when time is up between two batches. */
final class StopLoading extends \RuntimeException {}

/** Thrown inside the row sink when the table's count cannot be reconciled with the checkpoint. */
final class RestartTable extends \RuntimeException {}

/**
 * Reads one segment's lines as they arrive, skips the rows the checkpoint says are in, and inserts the rest in batches,
 * reporting every committed batch so the checkpoint moves with it. Holds the batch it is filling between chunks.
 */
final class SegmentLoader
{
    private string $buffer = '';

    private ?RowDecoder $decoder = null;

    private int $seen = 0;

    /** @var array<int, array<string, mixed>> */
    private array $batch = [];

    private int $bytes = 0;

    private bool $first = true;

    private readonly int $skip;

    /**
     * @param  array<string, int>  $cursor
     * @param  \Closure(array<string, int>): void  $moved  called with the new cursor after every committed batch
     */
    public function __construct(
        private readonly RowLoader $loader,
        private readonly string $table,
        private readonly int $startCount,
        private array $cursor,
        private readonly \Closure $moved,
        private readonly ImportContext $context,
    ) {
        $this->skip = (int) $cursor['done'];
    }

    public function feed(string $chunk): void
    {
        $this->buffer .= $chunk;

        while (($newline = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $newline);
            $this->buffer = substr($this->buffer, $newline + 1);

            if ($line !== '') {
                $this->line($line);
            }
        }
    }

    public function finish(): void
    {
        if ($this->buffer !== '') {
            $this->line($this->buffer);
            $this->buffer = '';
        }

        if (! $this->flush()) {
            throw new RestartTable;
        }
    }

    public function rowsSeen(): int
    {
        return $this->seen;
    }

    private function line(string $line): void
    {
        if ($this->decoder === null) {
            $this->decoder = RowDecoder::fromHeader($line);

            if ($this->decoder->table !== $this->table) {
                throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => "segment of {$this->table} names {$this->decoder->table}"]);
            }

            $this->loader->assertColumns($this->decoder);

            return;
        }

        $this->seen++;

        if ($this->seen <= $this->skip) {
            return; // already inserted, according to the checkpoint
        }

        $this->batch[] = array_combine($this->decoder->columns(), $this->decoder->decode($line));
        $this->bytes += strlen($line);

        if (count($this->batch) >= RowLoader::MAX_ROWS || $this->bytes >= RowLoader::MAX_BYTES) {
            if (! $this->flush()) {
                throw new RestartTable;
            }

            if ($this->context->expired()) {
                throw new StopLoading;
            }
        }
    }

    /** @return bool false when the table's count cannot be reconciled with the checkpoint */
    private function flush(): bool
    {
        if ($this->batch === []) {
            return true;
        }

        $n = count($this->batch);
        $insert = true;

        if ($this->first) {
            $this->first = false;

            if ($this->startCount === (int) $this->cursor['expected'] + $n) {
                $insert = false; // this very batch went in just before the last process died: do not insert it twice
            } elseif ($this->startCount !== (int) $this->cursor['expected']) {
                return false;
            }
        }

        if ($insert) {
            $this->loader->insert($this->table, $this->batch);
        }

        $this->cursor['done'] += $n;
        $this->cursor['expected'] += $n;
        $this->batch = [];
        $this->bytes = 0;
        ($this->moved)($this->cursor);

        return true;
    }
}
