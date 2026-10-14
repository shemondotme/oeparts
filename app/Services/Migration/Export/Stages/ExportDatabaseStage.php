<?php

namespace App\Services\Migration\Export\Stages;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\StageStepResult;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\GzipSegment;
use App\Services\Migration\Export\KeysetPager;
use App\Services\Migration\Export\RowEncoder;
use App\Services\Migration\Export\SecretsStaging;
use App\Services\Migration\Export\Transform\ExportPipelineFactory;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\KeyEscrow;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Copies the planned tables into compressed row files, a bounded amount per step, resumably.
 *
 * Tables go children first (the plan's order). Each table is read page by page by its key (no OFFSET),
 * every row passes the plan's rules (cut references, secrets, maintenance, credentials, numbering), is
 * encoded losslessly (RowEncoder) and appended to the open SEGMENT of that table as one gzip member.
 * A segment is closed at about 64 MiB compressed, or at the end of its table, and becomes one part of
 * the run: `db/<table>.<nnnn>` with its row count, SHA-256 and decompressed length (the package needs
 * the latter to inflate it safely).
 *
 * Resume is exact. The checkpoint holds the current table, the key of the last row written, how many
 * compressed bytes of the open segment are committed and the counts; every append first cuts the file
 * back to those bytes, so a step that died half way leaves neither a duplicate nor a torn page. The
 * checkpoint stays small however big the table is (it holds a key, not rows).
 *
 * A table whose id column is a single integer is read only up to the largest id it had when its turn
 * began (the high-water mark), so a busy shop that keeps adding orders cannot make the export chase them
 * for ever. The session is put in UTC so TIMESTAMP columns read the same here and on the target.
 */
class ExportDatabaseStage implements BackupStage
{
    private const INTEGER_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'];

    /** @param  \Closure(): string|null  $secretsKey  the package's secrets key; only called when secrets are included */
    public function __construct(
        private readonly GroupRegistry $registry,
        private readonly KeysetPager $pager,
        private readonly ?\Closure $secretsKey = null,
    ) {}

    public function key(): string
    {
        return BackupChunk::TYPE_DB;
    }

    public function step(BackupRun $run, array $state): StageStepResult
    {
        $plan = ExportPlan::fromArray((array) ($run->meta['export_plan'] ?? throw new \RuntimeException('This export run has no plan.')));
        $tables = $plan->tables;

        $state = $state === [] ? $this->initial() : $state;
        $this->useUtc();

        $parts = [];
        $deadline = microtime(true) + max(0.0, (float) config('migration.export.batch_seconds', 10));
        $built = ExportPipelineFactory::forPlan($plan, $this->secretSink($run, $plan), isset($run->meta['anonymise_salt']) ? (string) $run->meta['anonymise_salt'] : null, (bool) ($run->meta['raw'] ?? false));
        $notes = [];
        $current = null; // [table, encoder, key, transform]: schema work done once per table, not once per page

        while ($state['ti'] < count($tables)) {
            $table = $tables[$state['ti']];

            if (! $state['started']) {
                $state = $this->beginTable($table, $state);
            }

            if ($current === null || $current[0] !== $table) {
                $current = [$table, RowEncoder::forTable($table), $this->pager->keyOf($table), $built['pipeline']->forTable($table)];
            }

            [, $encoder, $key, $transform] = $current;

            // Open the segment with its header, once.
            if ($state['seg'] === null) {
                $state['seg'] = ['n' => $state['next_segment'], 'bytes' => 0, 'raw' => 0, 'rows' => 0];
                $state['next_segment']++;
                $header = $encoder->header($table, $this->sliceLabel($table, $plan), $key, (int) ($plan->estimate->tables[$table]['rows'] ?? 0))."\n";
                $state['seg']['bytes'] = GzipSegment::append($this->segmentPath($run, $table, $state['seg']['n']), 0, $header);
                $state['seg']['raw'] = strlen($header);
            }

            $page = $this->pager->page(
                $table, $key, $encoder->columns(), $state['cursor'], (int) config('migration.export.page_rows', 5000),
                (int) config('migration.export.page_bytes', 8 * 1024 * 1024), $state['hwm'], $this->constraint($table, $plan)
            );

            if ($page['rows'] === []) {
                $this->collect($parts, $this->sealSegment($run, $table, $state));
                $state['row_counts'][$table] = $state['rows_in_table'];
                $notes[] = "{$table}: {$state['rows_in_table']} rows";
                $state = $this->finishTable($state);

                if (microtime(true) >= $deadline) {
                    break;
                }

                continue;
            }

            $lines = '';
            foreach ($page['rows'] as $row) {
                $lines .= $encoder->line($transform !== null ? $transform($row) : $row)."\n";
            }

            $state['seg']['bytes'] = GzipSegment::append($this->segmentPath($run, $table, $state['seg']['n']), $state['seg']['bytes'], $lines);
            $state['seg']['raw'] += strlen($lines);
            $state['seg']['rows'] += count($page['rows']);
            $state['rows_in_table'] += count($page['rows']);
            $state['rows_total'] += count($page['rows']);
            $state['cursor'] = $page['last'];

            if ($state['seg']['bytes'] >= (int) config('migration.export.segment_bytes', 64 * 1024 * 1024)) {
                $this->collect($parts, $this->sealSegment($run, $table, $state));
                $state['seg'] = null;
            }

            if (microtime(true) >= $deadline) {
                break;
            }
        }

        $state['secrets'] = $this->mergeSecretsReport($state['secrets'], $built['secrets']->report());
        $done = $state['ti'] >= count($tables);

        if ($done) {
            $this->recordReport($run, $state);

            return new StageStepResult(done: true, state: [], part: array_pop($parts), message: 'Database copied: '.$state['rows_total'].' rows.', fraction: 1.0, parts: $parts);
        }

        $estimated = max(1, $plan->estimate->totalRows);
        $fraction = min(0.99, $state['rows_total'] / $estimated);
        $message = $notes !== [] ? implode('; ', array_slice($notes, -3)) : "Copying {$tables[$state['ti']]}";

        return StageStepResult::progress($state, array_pop($parts), $message, $fraction, $parts);
    }

    /** @return array<string, mixed> */
    private function initial(): array
    {
        return [
            'ti' => 0, 'started' => false, 'hwm' => null, 'cursor' => null, 'seg' => null, 'next_segment' => 0,
            'rows_in_table' => 0, 'rows_total' => 0, 'row_counts' => [],
            'secrets' => ['configured_keys' => [], 'included_keys' => [], 'undecryptable_keys' => []],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function beginTable(string $table, array $state): array
    {
        $state['started'] = true;
        $state['cursor'] = null;
        $state['seg'] = null;
        $state['next_segment'] = 0;
        $state['rows_in_table'] = 0;
        $state['hwm'] = $this->highWaterMark($table);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function finishTable(array $state): array
    {
        $state['ti']++;
        $state['started'] = false;
        $state['cursor'] = null;
        $state['hwm'] = null;
        $state['seg'] = null;
        $state['next_segment'] = 0;
        $state['rows_in_table'] = 0;

        return $state;
    }

    /** @return array<string, int|string>|null the largest id the table has now, for a single integer key; null otherwise */
    private function highWaterMark(string $table): ?array
    {
        $key = $this->pager->keyOf($table);

        if (count($key) !== 1) {
            return null;
        }

        foreach (Schema::getColumns($table) as $column) {
            if ($column['name'] === $key[0] && in_array(strtolower((string) $column['type_name']), self::INTEGER_TYPES, true)) {
                $max = DB::table($table)->max($key[0]);

                // An empty table: nothing to bound, and nothing will be read. An unsigned BIGINT id above the signed
                // range must stay its digits: casting it to int would saturate and silently cut off the last rows.
                $text = (string) ($max ?? 0);

                return [$key[0] => (string) (int) $text === $text ? (int) $text : $text];
            }
        }

        return null;
    }

    private function sliceLabel(string $table, ExportPlan $plan): ?string
    {
        return $table === GroupRegistry::SHARED_TABLE && $plan->seoSlice !== ExportPlan::SEO_ALL ? $plan->seoSlice : null;
    }

    /** @return (\Closure(Builder): void)|null */
    private function constraint(string $table, ExportPlan $plan): ?\Closure
    {
        if ($table !== GroupRegistry::SHARED_TABLE || $plan->seoSlice === ExportPlan::SEO_ALL || $plan->seoSlice === null) {
            return null;
        }

        $product = 'App\\Models\\Product';

        return $plan->seoSlice === ExportPlan::SEO_PRODUCT
            ? static fn ($query) => $query->where('metable_type', '=', $product)
            : static fn ($query) => $query->where('metable_type', '<>', $product);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>|null the part attributes of the sealed segment, null if it was already registered
     */
    private function sealSegment(BackupRun $run, string $table, array $state): ?array
    {
        $seg = $state['seg'];
        $sealed = GzipSegment::seal($this->segmentPath($run, $table, $seg['n']), $seg['bytes']);
        $relative = $this->relativePath($run, $table, $seg['n']);
        $group = $this->registry->ownerOf($table);

        // A kill between handing this part back and saving the checkpoint makes the next step seal the same
        // segment again; the part must be registered once.
        if ($run->parts()->where('path', $relative)->exists()) {
            return null;
        }

        return [
            'type' => BackupChunk::TYPE_DB,
            'name' => sprintf('db/%s.%04d', $table, $seg['n']),
            'disk' => $this->disk(),
            'path' => $relative,
            'sha256' => $sealed['sha256'],
            'bytes' => $sealed['bytes'],
            'rows' => $seg['rows'],
            'meta' => ['table' => $table, 'segment' => $seg['n'], 'codec' => 'gzip', 'raw_len' => $seg['raw'], 'group' => $group?->value],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     * @param  array<string, mixed>|null  $part
     */
    private function collect(array &$parts, ?array $part): void
    {
        if ($part !== null) {
            $parts[] = $part;
        }
    }

    private function disk(): string
    {
        return (string) config('backup.staging_disk', 'local');
    }

    private function relativePath(BackupRun $run, string $table, int $segment): string
    {
        return sprintf('backups/%d/export/db/%s.%04d.ndjson.gz', $run->getKey(), $table, $segment);
    }

    private function segmentPath(BackupRun $run, string $table, int $segment): string
    {
        return Storage::disk($this->disk())->path($this->relativePath($run, $table, $segment));
    }

    /** @return (\Closure(array{group: string, key: string, value: string}): void)|null */
    private function secretSink(BackupRun $run, ExportPlan $plan): ?\Closure
    {
        if (! $plan->includeSecrets) {
            return null;
        }

        $key = $this->secretsKey !== null
            ? ($this->secretsKey)()
            : KeyEscrow::forApplication()->require((string) ($run->meta['escrow_id'] ?? ''))->keys->secrets();

        $staging = new SecretsStaging(Storage::disk($this->disk())->path(sprintf('backups/%d/export/secrets.staging', $run->getKey())), $key);

        return static function (array $secret) use ($staging): void {
            $staging->append($secret);
        };
    }

    /**
     * @param  array<string, array<int, string>>  $kept
     * @param  array<string, array<int, string>>  $new
     * @return array<string, array<int, string>>
     */
    private function mergeSecretsReport(array $kept, array $new): array
    {
        foreach ($new as $name => $keys) {
            $kept[$name] = array_values(array_unique([...($kept[$name] ?? []), ...$keys]));
        }

        return $kept;
    }

    /** @param  array<string, mixed>  $state */
    private function recordReport(BackupRun $run, array $state): void
    {
        $meta = $run->meta ?? [];
        $meta['export_report'] = array_merge((array) ($meta['export_report'] ?? []), [
            'db' => ['rows_by_table' => $state['row_counts'], 'rows_total' => $state['rows_total']],
            'secrets' => $state['secrets'],
        ]);
        $run->meta = $meta;
    }

    private function useUtc(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("SET time_zone = '+00:00'");
        }
    }
}
