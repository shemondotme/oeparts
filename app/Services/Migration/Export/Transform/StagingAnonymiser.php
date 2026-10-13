<?php

namespace App\Services\Migration\Export\Transform;

use Illuminate\Support\Facades\Schema;

/**
 * Scrambles customer personal data on its way into a staging package, following PiiMap. It never reads a
 * value to decide whether it is personal: a column is either on the map (and is replaced) or it is not
 * (and is kept, and is listed as kept). Null stays null and an empty value stays empty, so optional
 * columns keep their shape.
 */
final class StagingAnonymiser implements RowTransformer
{
    /** @var array<string, array<string, mixed>> table => column => the neutral value a blanked column takes */
    private array $neutral = [];

    private ?string $unusablePassword = null;

    public function __construct(private readonly Pseudonymizer $pseudonyms) {}

    public function tables(): array
    {
        return PiiMap::tables();
    }

    public function transform(string $table, array $row): array
    {
        foreach (PiiMap::rulesFor($table) as $column => $rule) {
            if (! array_key_exists($column, $row)) {
                continue;
            }

            $row[$column] = $this->apply($table, $column, $rule, $row[$column]);
        }

        return $row;
    }

    private function apply(string $table, string $column, string $rule, mixed $value): mixed
    {
        if ($rule === PiiMap::PASSWORD) {
            return $this->unusablePassword ??= (string) password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 4]);
        }

        if ($rule === PiiMap::FORGET) {
            return null;
        }

        if ($rule === PiiMap::BLANK) {
            return $value === null ? null : $this->neutral($table, $column);
        }

        if ($value === null || $value === '') {
            return $value;
        }

        $text = (string) $value;
        $p = $this->pseudonyms;

        return match ($rule) {
            PiiMap::EMAIL => $p->email($text),
            PiiMap::NAME => $p->name($text),
            PiiMap::FIRST_NAME => $p->firstName(),
            PiiMap::LAST_NAME => $p->lastName($text),
            PiiMap::COMPANY => $p->company($text),
            PiiMap::STREET => $p->street($text),
            PiiMap::CITY => 'Anytown',
            PiiMap::POSTAL => '00000',
            PiiMap::PHONE => $p->phone($text),
            PiiMap::IP => $p->ip($text),
            PiiMap::VIN => $p->vin($text),
            PiiMap::CODE => $p->code($text),
            default => throw new \LogicException("No scrambling rule named {$rule}."),
        };
    }

    /** What a blanked column holds instead: NULL where it may be NULL, else an empty string, or an empty object for JSON. */
    private function neutral(string $table, string $column): mixed
    {
        if (! isset($this->neutral[$table])) {
            $this->neutral[$table] = [];

            foreach (Schema::getColumns($table) as $info) {
                $this->neutral[$table][(string) $info['name']] = ! $info['nullable']
                    ? (str_contains((string) $info['type_name'], 'json') ? '{}' : '')
                    : null;
            }
        }

        return $this->neutral[$table][$column] ?? '';
    }
}
