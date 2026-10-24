<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inserts one valid row into any table with nothing but the values a test cares about: every other NOT NULL column
 * gets a harmless filler (a number, a date, the first allowed value of an enum, a short string). Foreign keys are
 * deferred, so a test does not have to build a family of parent rows to prove something about one column.
 *
 * SQLite only (the filler reads an enum's allowed values from the table's own SQL).
 */
trait PlantsRows
{
    /**
     * An administrator row, plus (unless $spatieRole is null) the Spatie role that really makes it one. The legacy admins.role
     * column is only filled in so a test can prove the import never trusts it.
     *
     * @param  array<string, mixed>  $values
     */
    private function plantAdmin(array $values = [], ?string $spatieRole = 'super_admin'): int
    {
        $id = $this->plantRow('admins', $values + ['password' => '$2y$04$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234', 'is_active' => 1, 'role' => 'manager']);

        if ($spatieRole !== null) {
            $role = DB::table('roles')->where('name', $spatieRole)->where('guard_name', 'admin')->value('id')
                ?? DB::table('roles')->insertGetId(['name' => $spatieRole, 'guard_name' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => \App\Models\Admin::class, 'model_id' => $id]);
        }

        return $id;
    }

    private function deferForeignKeys(): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON'); // rolled back with the test transaction, never checked
    }

    /**
     * @param  array<string, mixed>  $values
     * @return int the id of the new row (0 for a table with no id column)
     */
    private function plantRow(string $table, array $values = []): int
    {
        $this->deferForeignKeys();
        $row = [];

        foreach (Schema::getColumns($table) as $column) {
            $name = (string) $column['name'];

            if (array_key_exists($name, $values)) {
                $row[$name] = $values[$name];

                continue;
            }

            if ($column['nullable'] || ($column['auto_increment'] ?? false) || $column['default'] !== null) {
                continue;
            }

            $row[$name] = $this->fillerFor($table, $column);
        }

        DB::table($table)->insert($row);

        return (int) (DB::table($table)->max('id') ?? 0);
    }

    /** @param  array<string, mixed>  $column */
    private function fillerFor(string $table, array $column): mixed
    {
        $name = (string) $column['name'];
        $type = (string) $column['type_name'];

        $sql = (string) DB::table('sqlite_master')->where('name', $table)->value('sql');
        if (preg_match('/"'.preg_quote($name, '/').'"\s+in\s*\(([^)]*)\)/i', $sql, $m) === 1) {
            return trim(trim(explode(',', $m[1])[0]), "'\"");
        }

        return match (true) {
            in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'boolean', 'decimal', 'float', 'double'], true) => 1,
            in_array($type, ['timestamp', 'datetime', 'date'], true) => now()->toDateTimeString(),
            $type === 'json' => '{}',
            default => 'x'.substr(md5($table.$name.microtime()), 0, 8),
        };
    }
}
