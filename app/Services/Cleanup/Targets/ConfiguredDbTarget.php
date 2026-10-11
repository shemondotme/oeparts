<?php

namespace App\Services\Cleanup\Targets;

use App\Models\CleanupRun;
use App\Services\Backup\BackupRetentionService;
use App\Services\Cleanup\CleanupContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A table target described by an array in config('cleanup.targets') instead of a class of its own —
 * almost every log/history table is "delete rows older than N days", so the whole registry reads as
 * one declarative list.
 *
 * Definition keys:
 *   key, label, description, group        identity / where it is listed ('logs'|'history'|'system'|'backups')
 *   table, column [, pk]                  what to prune and which timestamp ages it
 *   days, floor                           default retention and the shortest an operator may set
 *   truncatable                           may "Truncate all" be offered (telemetry only)
 *   auto                                  included in the scheduled run by default (default true)
 *   scope                                 a NAMED extra predicate (see scope()); unknown names are refused
 *   keep                                  a NAMED keep-rule (see keepIds()); unknown names are refused
 *
 * Predicates and keep-rules are looked up by name here rather than passed in as closures or SQL, so a
 * definition can never smuggle arbitrary SQL into the engine.
 */
class ConfiguredDbTarget extends DbPruneTarget
{
    /** @var array<string, mixed> */
    private array $def;

    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(array $definition)
    {
        foreach (['key', 'label', 'table', 'column', 'days'] as $required) {
            if (! isset($definition[$required])) {
                throw new InvalidArgumentException("Cleanup target definition is missing '{$required}'.");
            }
        }

        $this->def = $definition;

        parent::__construct(); // refuses business tables
    }

    public function key(): string
    {
        return (string) $this->def['key'];
    }

    public function label(): string
    {
        return (string) $this->def['label'];
    }

    public function description(): string
    {
        return (string) ($this->def['description'] ?? '');
    }

    public function group(): string
    {
        return (string) ($this->def['group'] ?? 'logs');
    }

    public function defaultDays(): int
    {
        return (int) $this->def['days'];
    }

    public function floorDays(): int
    {
        return (int) ($this->def['floor'] ?? 0);
    }

    public function truncatable(): bool
    {
        return (bool) ($this->def['truncatable'] ?? false);
    }

    public function autoByDefault(): bool
    {
        return (bool) ($this->def['auto'] ?? true);
    }

    protected function table(): string
    {
        return (string) $this->def['table'];
    }

    protected function ageColumn(): string
    {
        return (string) $this->def['column'];
    }

    protected function primaryKey(): string
    {
        return (string) ($this->def['pk'] ?? 'id');
    }

    protected function scope(Builder $query): Builder
    {
        return match ($this->def['scope'] ?? null) {
            null => $query,
            // Only runs that are finished: a 'running' import is also the lock that stops a second one.
            'terminal_import_runs' => $query->whereIn('status', ['success', 'failed']),
            'terminal_updates' => $query->whereIn('status', ['success', 'failed', 'rolled_back']),
            'terminal_cleanup_runs' => $query->whereIn('status', CleanupRun::TERMINAL_STATUSES),
            // Backup runs whose files are already gone (pruned by retention / reclaimed by the janitor): only
            // the history row is left.
            'backup_history' => $query
                ->whereNotIn('status', ['pending', 'running'])
                ->where(function (Builder $w) {
                    $w->whereNotNull('meta->pruned_at')->orWhereNotNull('meta->cleaned_at');
                }),
            'expiring_tokens' => $query->whereNotNull('expires_at'),
            default => throw new InvalidArgumentException("Unknown cleanup scope '{$this->def['scope']}' on target '{$this->key()}'."),
        };
    }

    /**
     * @return list<int>
     */
    protected function keepIds(CleanupContext $ctx): array
    {
        $rule = (string) ($this->def['keep'] ?? '');
        if ($rule === '') {
            return [];
        }

        $table = $this->table();
        $pk = $this->primaryKey();
        $column = $this->ageColumn();

        // newest:N — always keep the N most recent rows (e.g. the dashboards read the last 12 snapshots).
        if (preg_match('/^newest:(\d{1,3})$/', $rule, $m) === 1) {
            return $this->ids(DB::table($table)->orderByDesc($column)->orderByDesc($pk)->limit((int) $m[1]));
        }

        // newest_per:<column>:N — the N most recent rows for each value of <column> (health snapshots keep a
        // history PER check, and the alert logic compares against the latest of each).
        if (preg_match('/^newest_per:([a-z_]+):(\d{1,3})$/', $rule, $m) === 1) {
            $ids = [];
            foreach (DB::table($table)->distinct()->pluck($m[1]) as $value) {
                $ids = [...$ids, ...$this->ids(
                    DB::table($table)->where($m[1], $value)->orderByDesc($column)->orderByDesc($pk)->limit((int) $m[2])
                )];
            }

            return $ids;
        }

        return match ($rule) {
            // Backup runs an unfinished (or the latest) update/restore may still need for a rollback.
            'backup_protected' => app(BackupRetentionService::class)->updateReferencedRunIds(),
            // Cleanup History: the 50 newest runs, plus the newest SUCCESS of every target ("last cleaned").
            'cleanup_history' => $this->cleanupHistoryKeep(),
            default => throw new InvalidArgumentException("Unknown cleanup keep-rule '{$rule}' on target '{$this->key()}'."),
        };
    }

    /**
     * @return list<int>
     */
    private function cleanupHistoryKeep(): array
    {
        // Newest 50 FINISHED runs: the run doing this very cleanup is still active and must not use up a slot.
        $ids = $this->ids(DB::table('cleanup_runs')->whereIn('status', CleanupRun::TERMINAL_STATUSES)->orderByDesc('id')->limit(50));

        $latestSuccess = DB::table('cleanup_run_items')
            ->where('status', 'success')
            ->select('target', DB::raw('MAX(run_id) as run_id'))
            ->groupBy('target')
            ->pluck('run_id');

        foreach ($latestSuccess as $runId) {
            $ids[] = (int) $runId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    private function ids(Builder $query): array
    {
        return $query->pluck($this->primaryKey())->map(fn ($id) => (int) $id)->all();
    }
}
