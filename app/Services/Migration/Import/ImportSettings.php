<?php

namespace App\Services\Migration\Import;

use Illuminate\Support\Facades\DB;

/**
 * Reads and writes stored settings the way the finishing stages need: straight against the table, never through
 * SettingsService (which masks values on a copy, caches, and fires observers). What is stored is what is read.
 */
final class ImportSettings
{
    public function stored(string $group, string $key): ?string
    {
        $value = DB::table('settings')->where('group', $group)->where('key', $key)->value('value');

        return $value === null ? null : (string) $value;
    }

    public function exists(string $group, string $key): bool
    {
        return DB::table('settings')->where('group', $group)->where('key', $key)->exists();
    }

    /** Set a value, creating the row if it is not there. @return bool true when the stored value changed */
    public function put(string $group, string $key, string $value, string $type = 'string'): bool
    {
        $row = DB::table('settings')->where('group', $group)->where('key', $key)->first(['id', 'value']);

        if ($row === null) {
            DB::table('settings')->insert(['group' => $group, 'key' => $key, 'value' => $value, 'type' => $type, 'is_encrypted' => false, 'created_at' => now(), 'updated_at' => now()]);

            return true;
        }

        if ((string) $row->value === $value && $row->value !== null) {
            return false;
        }

        DB::table('settings')->where('id', $row->id)->update(['value' => $value, 'updated_at' => now()]);

        return true;
    }
}
