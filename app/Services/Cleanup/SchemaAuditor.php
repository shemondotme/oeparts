<?php

namespace App\Services\Cleanup;

use Illuminate\Support\Facades\Schema;

/**
 * Report-only database audit for the admin Cleanup dashboard — NEVER deletes a
 * table or a row. Flags tables that exist in the live database but that no
 * migration in this install's own history is responsible for, so an operator
 * can look into them (a one-off table created by hand in phpMyAdmin, a leftover
 * from an unrelated tool, …) and decide for themselves whether to keep or drop
 * it. Deliberately conservative: migrations only ever ADD (rule #42, idempotent
 * + reversible), so under normal operation this list should always be empty —
 * anything on it is a genuine "where did this come from?" signal, not routine
 * noise, and is never acted on automatically.
 */
class SchemaAuditor
{
    /** Overridable so tests can point the scanner at small synthetic fixture migrations. */
    public function __construct(private readonly ?string $migrationsPath = null) {}

    /**
     * Spatie Permission's own migration resolves its table names at runtime via
     * config('permission.table_names') (`Schema::create($tableNames['permissions'], ...)`),
     * not as a literal string — expectedTables()'s static scan can't see these, so
     * they're listed by hand. Matches this app's actual (default, unmodified)
     * permission.php config — a failing PermissionMatrixTest or a config change
     * would be the signal to update this list.
     */
    private const DYNAMIC_PACKAGE_TABLES = [
        'permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions',
    ];

    /** Bootstrapped by the migration runner itself before any migration file ever executes — nothing creates it. */
    private const FRAMEWORK_TABLES = ['migrations'];

    /**
     * Tables this install's own migration history is expected to have created:
     * every literal `Schema::create('name', ...)` (and this codebase's own
     * `SafeSchema::create('context', 'name', ...)` wrapper, rule #42 — see
     * App\Support\Database\SafeSchema — whose table name is its SECOND
     * argument) found in a migration's up() method, minus:
     *   - any later migration's up() that `Schema::drop(IfExists)?('name')`s it
     *     again (a genuine, forward, schema-shrinking migration — e.g. this
     *     project's own drop_admin_dashboards_table), and
     *   - a name a later `Schema::rename('from', 'to')` moved away from (a
     *     temp-table-swap ALTER pattern, e.g. this project's own
     *     add_paysera_to_payments_gateway_enum — the temp name never ends up
     *     "expected", the renamed-to name does).
     * A migration's OWN down() method is deliberately excluded from the scan:
     * `down()` dropping what that same migration's `up()` just created is the
     * rollback path, not a schema change, and must never affect what we expect
     * to currently exist.
     *
     * The negative lookbehind on the first pattern stops it matching INSIDE
     * `SafeSchema::create(` (a plain substring of which is `Schema::create(`) —
     * found the hard way: it was silently capturing SafeSchema's first
     * argument (the human-readable context label) as if it were a table name.
     *
     * Matches are applied in the ORDER THEY ACTUALLY APPEAR in the source, not
     * grouped by pattern type — also found the hard way, via a migration whose
     * up() has a per-driver branch (MySQL: a plain ALTER; SQLite, which can't
     * ALTER a column's enum constraint: drop then recreate the same table) —
     * applying every create before every drop regardless of which came first in
     * the text undid that migration's own net effect and wrongly flagged a
     * table that plainly still exists as "unexpected."
     *
     * @return list<string>
     */
    public function expectedTables(): array
    {
        $tables = [];

        foreach ($this->migrationFiles() as $file) {
            foreach ($this->schemaChangesInOrder((string) file_get_contents($file)) as $change) {
                if ($change['op'] === 'drop') {
                    unset($tables[$change['table']]);
                } else {
                    unset($tables[$change['from'] ?? $change['table']]);
                    $tables[$change['table']] = true;
                }
            }
        }

        foreach ([...self::DYNAMIC_PACKAGE_TABLES, ...self::FRAMEWORK_TABLES] as $table) {
            $tables[$table] = true;
        }

        return array_keys($tables);
    }

    /**
     * Every Schema::create() / SafeSchema::create() / Schema::drop(IfExists)?() /
     * Schema::rename() call in a migration's up() method, in the order they
     * appear in the source.
     *
     * @return list<array{op: 'create'|'drop', table: string, from?: string}>
     */
    private function schemaChangesInOrder(string $source): array
    {
        $up = $this->upMethodBody($source);
        $patterns = [
            "/(?<![A-Za-z])Schema::create\('([a-zA-Z0-9_]+)'/" => 'create',
            "/SafeSchema::create\('[^']*',\s*'([a-zA-Z0-9_]+)'/" => 'create',
            '/Schema::drop(?:IfExists)?\(\'([a-zA-Z0-9_]+)\'/' => 'drop',
            "/Schema::rename\('([a-zA-Z0-9_]+)',\s*'([a-zA-Z0-9_]+)'\)/" => 'rename',
        ];

        $found = [];
        foreach ($patterns as $pattern => $op) {
            if (! preg_match_all($pattern, $up, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $match) {
                $found[] = $op === 'rename'
                    ? ['offset' => $match[0][1], 'op' => 'create', 'table' => $match[2][0], 'from' => $match[1][0]]
                    : ['offset' => $match[0][1], 'op' => $op, 'table' => $match[1][0]];
            }
        }

        usort($found, fn (array $a, array $b) => $a['offset'] <=> $b['offset']);

        return array_map(fn (array $c) => array_diff_key($c, ['offset' => null]), $found);
    }

    /**
     * @return array{
     *     expected_missing: list<string>,
     *     unexpected: list<string>,
     * }
     */
    public function audit(): array
    {
        $expected = $this->expectedTables();
        $live = Schema::getTableListing(schemaQualified: false);

        return [
            // A table every migration up to here says should exist, but doesn't — a
            // real integrity problem (not file cleanup), surfaced here because it's
            // the same signal in reverse and an operator looking at this page should
            // see it too.
            'expected_missing' => array_values(array_diff($expected, $live)),
            'unexpected' => array_values(array_diff($live, $expected)),
        ];
    }

    /** @return list<string> */
    private function migrationFiles(): array
    {
        $files = glob(($this->migrationsPath ?? database_path('migrations')).'/*.php') ?: [];
        sort($files); // filename-timestamp order == the order migrate actually runs them in

        return $files;
    }

    /** The source between `function up(` and the next `function down(` (or EOF if there isn't one). */
    private function upMethodBody(string $source): string
    {
        if (! preg_match('/function\s+up\s*\([^)]*\)[^{]*\{/', $source, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $start = $m[0][1] + strlen((string) $m[0][0]);
        $downStart = preg_match('/function\s+down\s*\(/', $source, $dm, PREG_OFFSET_CAPTURE, $start)
            ? $dm[0][1]
            : strlen($source);

        return substr($source, $start, $downStart - $start);
    }
}
