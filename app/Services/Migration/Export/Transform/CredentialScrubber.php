<?php

namespace App\Services\Migration\Export\Transform;

/**
 * Throws away session credentials that mean nothing on another server and only widen what a stolen
 * package would give away: "remember me" tokens. Password hashes stay (admins and customers must be able
 * to log in on the new site); the scrambled staging copy replaces those separately.
 */
final class CredentialScrubber implements RowTransformer
{
    private const COLUMNS = ['admins' => ['remember_token'], 'users' => ['remember_token']];

    public function tables(): array
    {
        return array_keys(self::COLUMNS);
    }

    public function transform(string $table, array $row): array
    {
        foreach (self::COLUMNS[$table] ?? [] as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = null;
            }
        }

        return $row;
    }
}
