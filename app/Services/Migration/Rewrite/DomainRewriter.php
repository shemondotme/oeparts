<?php

namespace App\Services\Migration\Rewrite;

use App\Enums\SettingType;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rewrites the address of the site inside the database: the settings, then every registered table, walked by
 * primary key in small chunks.
 *
 * How it stays safe:
 *  - it goes through the query builder only. No model is loaded, so no observer runs: nothing logs an activity,
 *    busts a cache or pushes an IndexNow ping for thousands of rows that merely had their links updated;
 *  - every update is guarded by the value that was read ("UPDATE ... WHERE id = ? AND col = <the old value>"): a row
 *    somebody edited in the meantime is skipped and counted, never overwritten, and a later pass picks it up;
 *  - each chunk is committed by itself and the cursor records the last key, so a run that is stopped and resumed
 *    ends exactly where an uninterrupted one would. Rewriting is idempotent, so doing a chunk twice is harmless;
 *  - a dry run walks the same way and only counts, so what it reports is what an apply run changes;
 *  - only rows that mention the old name are read (a LIKE on the registered columns), so the bulk table is not
 *    pulled through PHP row by row.
 */
final class DomainRewriter
{
    public const SETTINGS = 'settings';

    /** @var array<string, array<string, string>> table => column => type name */
    private array $columnTypes = [];

    /**
     * A seam for tests only: called with (table, id) after a row was read and before it is written, which is exactly
     * the moment a concurrent edit can happen. Nothing in the application sets it.
     *
     * @var (\Closure(string, mixed): void)|null
     */
    public ?\Closure $beforeWrite = null;

    public function __construct(
        private readonly DomainRewriteRegistry $registry = new DomainRewriteRegistry,
        private readonly ?string $connection = null,
        private readonly int $chunkRows = 200,
    ) {}

    /** @return array<int, string> every step in the order it runs: the settings, small tables, then the bulk tier */
    public function steps(): array
    {
        $small = [];
        $bulk = [];

        foreach ($this->registry->targets() as $table => $target) {
            if ($target->tier === RewriteTarget::BULK) {
                $bulk[] = $table;
            } else {
                $small[] = $table;
            }
        }

        return [self::SETTINGS, ...$small, ...$bulk];
    }

    /**
     * Count what an apply run would change, writing nothing.
     */
    public function preview(RewritePlan $plan): RewriteCursor
    {
        $cursor = RewriteCursor::fresh($plan);

        while (! $cursor->done) {
            $this->advance($plan, $cursor, PHP_FLOAT_MAX, true);
        }

        return $cursor;
    }

    /**
     * Do work until the budget (seconds) is spent or everything is done. At least one chunk is always processed, so
     * a zero budget still moves.
     *
     * @param  (\Closure(RewriteCursor): void)|null  $afterChunk  called after every chunk, with the cursor to save
     */
    public function advance(RewritePlan $plan, RewriteCursor $cursor, float $budgetSeconds = 10.0, bool $dryRun = false, ?\Closure $afterChunk = null): RewriteCursor
    {
        if ($cursor->planHash !== $plan->hash()) {
            throw new \LogicException('This cursor was made for a different rewrite plan.');
        }

        $rewriter = $plan->rewriter();
        $steps = $this->steps();
        $deadline = microtime(true) + $budgetSeconds;

        do {
            if ($cursor->step >= count($steps)) {
                $this->finish($cursor);
                if ($afterChunk !== null) {
                    $afterChunk($cursor);
                }

                break;
            }

            $step = $steps[$cursor->step];

            [$lastId, $more] = $step === self::SETTINGS
                ? $this->settingsChunk($plan, $rewriter, $cursor, $dryRun)
                : $this->tableChunk($step, $rewriter, $cursor, $dryRun);

            if ($more) {
                $cursor->lastId = $lastId;
            } else {
                $cursor->step++;
                $cursor->lastId = 0;
            }

            if ($cursor->step >= count($steps)) {
                $this->finish($cursor);
            }

            if ($afterChunk !== null) {
                $afterChunk($cursor);
            }
        } while (! $cursor->done && microtime(true) < $deadline);

        return $cursor;
    }

    private function finish(RewriteCursor $cursor): void
    {
        $cursor->done = true;
        $cursor->finishedAt ??= now()->toIso8601String();
    }

    // ---- the settings ------------------------------------------------------------------------------------

    /** @return array{0: int, 1: bool} last id handled, and whether there may be more */
    private function settingsChunk(RewritePlan $plan, UrlRewriter $rewriter, RewriteCursor $cursor, bool $dryRun): array
    {
        $rows = $this->db()->table('settings')
            ->where('id', '>', $cursor->lastId)
            ->orderBy('id')
            ->limit($this->chunkRows)
            ->get(['id', 'group', 'key', 'value', 'type', 'is_encrypted']);

        $last = $cursor->lastId;

        foreach ($rows as $row) {
            $last = (int) $row->id;
            $cursor->bump(self::SETTINGS, 'rows_seen');

            if ($row->is_encrypted || (string) $row->type === SettingType::Encrypted->value || $row->value === null) {
                continue;
            }

            $old = (string) $row->value;
            $new = $this->newSettingValue($plan, $rewriter, (string) $row->group, (string) $row->key, (string) $row->type, $old, $cursor);

            if ($new === null || $new === $old) {
                continue;
            }

            $cursor->bump(self::SETTINGS, 'rows_changed');

            if ($dryRun) {
                continue;
            }

            $affected = $this->db()->table('settings')->where('id', $row->id)->where('value', $old)->update(['value' => $new]);

            if ($affected === 0) {
                $cursor->bump(self::SETTINGS, 'rows_changed', -1);
                $cursor->bump(self::SETTINGS, 'concurrent_skips');
            }
        }

        return [$last, $rows->count() === $this->chunkRows];
    }

    /** The value to store, or null to leave the row alone. */
    private function newSettingValue(RewritePlan $plan, UrlRewriter $rewriter, string $group, string $key, string $type, string $old, RewriteCursor $cursor): ?string
    {
        $rule = $this->registry->settingRule($group, $key);

        if ($rule !== null) {
            return match ($rule[0]) {
                SettingAction::Set => $plan->to,
                SettingAction::Clear => $old === '' ? null : '',
                SettingAction::Url, SettingAction::Css => $this->plain($rewriter, $old, $cursor),
                SettingAction::CanonicalHost => $this->left($cursor, 'canonical_host_left'),
                SettingAction::ScanOnly, SettingAction::Email, SettingAction::Ignore => null,
            };
        }

        if ($old === '') {
            return null;
        }

        return match ($type) {
            SettingType::String->value => $this->plain($rewriter, $old, $cursor),
            SettingType::Json->value => $this->json($rewriter, $old, $cursor),
            default => null,
        };
    }

    private function left(RewriteCursor $cursor, string $counter): null
    {
        $cursor->bump(self::SETTINGS, $counter);

        return null;
    }

    private function plain(UrlRewriter $rewriter, string $old, RewriteCursor $cursor): ?string
    {
        $count = 0;
        $new = $rewriter->rewrite($old, $count);
        $cursor->bump(self::SETTINGS, 'replacements', $count);

        return $count === 0 ? null : $new;
    }

    private function json(UrlRewriter $rewriter, string $old, RewriteCursor $cursor): ?string
    {
        $outcome = $rewriter->rewriteJson($old);

        if ($outcome->invalidJson) {
            $cursor->bump(self::SETTINGS, 'invalid_json');

            return null;
        }

        $cursor->bump(self::SETTINGS, 'replacements', $outcome->replacements);

        return $outcome->changed($old) ? $outcome->text : null;
    }

    // ---- the registered tables ---------------------------------------------------------------------------

    /** @return array{0: int, 1: bool} */
    private function tableChunk(string $table, UrlRewriter $rewriter, RewriteCursor $cursor, bool $dryRun): array
    {
        $target = $this->registry->target($table);

        if ($target === null || ! Schema::connection($this->connection)->hasTable($table)) {
            $cursor->bump($table, 'missing_table');

            return [0, false];
        }

        $columns = array_values(array_filter(
            $target->columnNames(),
            fn (string $column): bool => Schema::connection($this->connection)->hasColumn($table, $column),
        ));

        if ($columns === []) {
            $cursor->bump($table, 'missing_table');

            return [0, false];
        }

        $needles = $rewriter->prefilterNeedles();
        $pk = $target->primaryKey;

        $query = $this->db()->table($table)
            ->select([$pk, ...$columns])
            ->where($pk, '>', $cursor->lastId);

        foreach ($target->where as $column => $value) {
            $query->where($column, $value);
        }

        // Only rows that mention the old name at all: on a table of a million rows nearly all of them do not.
        $query->where(function ($mentions) use ($columns, $needles): void {
            foreach ($columns as $column) {
                foreach ($needles as $needle) {
                    $mentions->orWhere($column, 'like', '%'.$needle.'%');
                }
            }
        });

        $rows = $query->orderBy($pk)->limit($this->chunkRows)->get();
        $last = $cursor->lastId;

        foreach ($rows as $row) {
            $last = (int) $row->{$pk};
            $cursor->bump($table, 'rows_seen');

            $changes = [];
            $olds = [];
            $replacements = 0;

            foreach ($columns as $column) {
                $old = $row->{$column};

                if (! is_string($old) || $old === '') {
                    continue;
                }

                $kind = $target->columns[$column];

                if ($kind->isJson()) {
                    $outcome = $rewriter->rewriteJson($old);

                    if ($outcome->invalidJson) {
                        $cursor->bump($table, 'invalid_json');

                        continue;
                    }

                    if ($outcome->changed($old)) {
                        $changes[$column] = $outcome->text;
                        $olds[$column] = $old;
                        $replacements += $outcome->replacements;
                    }

                    continue;
                }

                $count = 0;
                $new = $rewriter->rewrite($old, $count);

                if ($count > 0 && $new !== $old) {
                    $changes[$column] = $new;
                    $olds[$column] = $old;
                    $replacements += $count;
                }
            }

            if ($changes === []) {
                continue;
            }

            if (! $dryRun && $this->guardedUpdate($table, $pk, $row->{$pk}, $changes, $olds) === 0) {
                $cursor->bump($table, 'concurrent_skips');

                continue;
            }

            $cursor->bump($table, 'rows_changed');
            $cursor->bump($table, 'replacements', $replacements);
        }

        return [$last, $rows->count() === $this->chunkRows];
    }

    /**
     * "UPDATE table SET ... WHERE pk = ? AND column = <what was read>": if the row changed since it was read, nothing
     * matches and nothing is written.
     *
     * @param  array<string, string>  $changes
     * @param  array<string, string>  $olds
     */
    private function guardedUpdate(string $table, string $pk, mixed $id, array $changes, array $olds): int
    {
        if ($this->beforeWrite !== null) {
            ($this->beforeWrite)($table, $id);
        }

        $query = $this->db()->table($table)->where($pk, $id);
        $grammar = $this->db()->getQueryGrammar();

        foreach ($olds as $column => $old) {
            if ($this->isTrueJsonColumn($table, $column)) {
                // MySQL compares a JSON column with a string by treating the string as a JSON string, never equal; cast it.
                $query->whereRaw($grammar->wrap($column).' = CAST(? AS JSON)', [$old]);
            } else {
                $query->where($column, $old);
            }
        }

        return $query->update($changes);
    }

    private function isTrueJsonColumn(string $table, string $column): bool
    {
        if ($this->db()->getDriverName() !== 'mysql') {
            return false;
        }

        if (! isset($this->columnTypes[$table])) {
            $this->columnTypes[$table] = [];

            foreach (Schema::connection($this->connection)->getColumns($table) as $info) {
                $this->columnTypes[$table][(string) $info['name']] = (string) $info['type_name'];
            }
        }

        return ($this->columnTypes[$table][$column] ?? '') === 'json';
    }

    private function db(): Connection
    {
        return DB::connection($this->connection);
    }
}
