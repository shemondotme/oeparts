<?php

namespace App\Services\Migration\Export;

use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The references a foreign key cannot express. Each entry names the group whose data holds the
 * reference and the group holding what it points at; selecting the first without the second produces a
 * warning with a count. Warnings never block: the person decides whether the logo matters.
 *
 * Entries here were checked against the real schema (a test fails if a column or setting disappears).
 * An operator can add more in config('migration.export.json_refs') for JSON columns this code does not
 * know about, for example a custom section that stores an image path.
 *
 * Counting a big table exactly is a full scan, so below `$exactBelowRows` rows the count is exact and
 * above it the table's row estimate is reported as an upper bound, marked approximate.
 */
final class LogicalRefRegistry
{
    public const EXACT_BELOW_ROWS = 200_000;

    /** @var \Closure(string): int|null */
    private readonly ?\Closure $estimate;

    /**
     * @param  \Closure(string): int|null  $rowEstimate  table => estimated rows; only tests pass this
     */
    public function __construct(
        private readonly GroupRegistry $groups,
        private readonly int $exactBelowRows = self::EXACT_BELOW_ROWS,
        ?\Closure $rowEstimate = null,
    ) {
        $this->estimate = $rowEstimate;
    }

    /** @return array<int, LogicalRef> */
    public function all(): array
    {
        return [...self::builtIn(), ...$this->configured()];
    }

    /**
     * @return array<int, LogicalWarning> in the order of all()
     */
    public function warningsFor(Resolution $resolution): array
    {
        $warnings = [];

        foreach ($this->all() as $ref) {
            if (! $resolution->includes($ref->from) || $resolution->includes($ref->target)) {
                continue;
            }

            [$count, $approximate] = $this->count($ref);

            if ($count > 0) {
                $warnings[] = new LogicalWarning($ref, $count, $approximate);
            }
        }

        return $warnings;
    }

    /** @return array<int, LogicalRef> */
    public static function builtIn(): array
    {
        return [
            new LogicalRef('site_logo', LogicalRef::SETTING, 'general.logo_id', null, null, GroupKey::Core, GroupKey::Media),
            new LogicalRef('site_favicon', LogicalRef::SETTING, 'general.favicon_id', null, null, GroupKey::Core, GroupKey::Media),
            new LogicalRef('product_pictures', LogicalRef::COLUMN, 'product_images', 'path', null, GroupKey::Catalog, GroupKey::CatalogFiles),
            new LogicalRef('refund_pictures', LogicalRef::JSON_LIST, 'refund_requests', 'return_images', null, GroupKey::Sales, GroupKey::SalesFiles),
            new LogicalRef('payment_proofs', LogicalRef::JSON_PATH, 'payments', 'gateway_response', 'proof_path', GroupKey::Sales, GroupKey::SalesFiles),
        ];
    }

    /** @return array<int, LogicalRef> */
    private function configured(): array
    {
        $refs = [];

        foreach ((array) config('migration.export.json_refs', []) as $index => $entry) {
            $refs[] = $this->fromConfig($index, (array) $entry);
        }

        return $refs;
    }

    /** @param  array<string, mixed>  $entry */
    private function fromConfig(int|string $index, array $entry): LogicalRef
    {
        $where = "config migration.export.json_refs[{$index}]";

        foreach (['table', 'column', 'from', 'target', 'id'] as $required) {
            if (! isset($entry[$required]) || ! is_string($entry[$required]) || $entry[$required] === '') {
                throw new \InvalidArgumentException("{$where} needs a '{$required}'.");
            }
        }

        if (! in_array($entry['table'], $this->groups->allTables(), true)) {
            throw new \InvalidArgumentException("{$where}: '{$entry['table']}' is not a known table.");
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $entry['id']) !== 1 || preg_match('/^[a-z_][a-z0-9_]*$/', $entry['column']) !== 1) {
            throw new \InvalidArgumentException("{$where}: id and column must be plain lower-case names.");
        }

        $jsonPath = isset($entry['json_path']) ? (string) $entry['json_path'] : null;
        if ($jsonPath !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $jsonPath) !== 1) {
            throw new \InvalidArgumentException("{$where}: json_path must be one plain key.");
        }

        return new LogicalRef(
            $entry['id'], $jsonPath !== null ? LogicalRef::JSON_PATH : LogicalRef::COLUMN, $entry['table'], $entry['column'], $jsonPath,
            GroupKey::from($entry['from']), GroupKey::from($entry['target'])
        );
    }

    /** @return array{0: int, 1: bool} count and whether it is an estimate */
    private function count(LogicalRef $ref): array
    {
        if ($ref->kind === LogicalRef::SETTING) {
            [$group, $key] = explode('.', $ref->subject, 2);
            $value = DB::table('settings')->where('group', $group)->where('key', $key)->value('value');

            return [trim((string) $value) !== '' ? 1 : 0, false];
        }

        $rows = $this->estimatedRows($ref->subject);

        if ($rows >= $this->exactBelowRows) {
            return [$rows, true];
        }

        return [$this->presentRows($ref), false];
    }

    private function presentRows(LogicalRef $ref): int
    {
        $query = DB::table($ref->subject);
        $column = (string) $ref->column;

        match ($ref->kind) {
            LogicalRef::COLUMN => $query->whereNotNull($column)->where($column, '<>', ''),
            LogicalRef::JSON_LIST => $query->whereNotNull($column)->whereJsonLength($column, '>', 0),
            default => $this->whereJsonStringPresent($query, $column, (string) $ref->jsonPath),
        };

        return $query->count();
    }

    private function whereJsonStringPresent(Builder $query, string $column, string $path): void
    {
        $wrapped = $query->getGrammar()->wrap($column);

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // A JSON null is not an SQL NULL on MySQL: ask for the JSON type.
            $query->whereRaw("JSON_TYPE(JSON_EXTRACT({$wrapped}, ?)) = 'STRING'", ['$.'.$path])
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$wrapped}, ?)) <> ''", ['$.'.$path]);

            return;
        }

        $query->whereRaw("json_extract({$wrapped}, ?) IS NOT NULL", ['$.'.$path])
            ->whereRaw("json_extract({$wrapped}, ?) <> ''", ['$.'.$path]);
    }

    private function estimatedRows(string $table): int
    {
        if ($this->estimate !== null) {
            return ($this->estimate)($table);
        }

        $connection = DB::connection();

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $rows = $connection->selectOne(
                'SELECT TABLE_ROWS AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );

            return (int) ($rows->n ?? 0);
        }

        return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
    }
}
