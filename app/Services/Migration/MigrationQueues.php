<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\Log;

/**
 * The queue names the migration feature works with, read from config in ONE place so that the freeze
 * (which pauses the customer queues), the import guard and the documentation can never disagree.
 *
 *   migration  carries every migration job and nothing else.
 *   customer   everything customers' work runs on: the ONLY queues a freeze or an import may pause.
 *   probe      the queue the readiness probe uses; one of the customer queues (every worker line ever
 *              shipped serves `default`, so a probe there works on hosts that never edited their command).
 *
 * The migration queue can never be among the customer queues: a configuration that lists it there would
 * make a freeze pause the very queue its export runs on. It is removed, and the mistake is logged.
 */
final class MigrationQueues
{
    private static bool $warned = false;

    public static function migration(): string
    {
        $name = trim((string) config('migration.queues.migration', 'migration'));

        return $name === '' ? 'migration' : $name;
    }

    /** @return array<int, string> */
    public static function customer(): array
    {
        $names = array_values(array_unique(array_filter(
            array_map(static fn (mixed $q): string => trim((string) $q), (array) config('migration.queues.customer', ['critical', 'default', 'low'])),
            static fn (string $q): bool => $q !== ''
        )));

        $migration = self::migration();

        if (in_array($migration, $names, true)) {
            if (! self::$warned) {
                self::$warned = true;
                Log::channel('migration')->warning("The migration queue '{$migration}' was listed among the customer queues and was removed: pausing it would stop the export a freeze is for.");
            }

            $names = array_values(array_filter($names, static fn (string $q): bool => $q !== $migration));
        }

        return $names === [] ? ['default'] : $names;
    }

    /** The queue of the readiness probe: the configured one if it is a customer queue, else the first customer queue. */
    public static function probe(): string
    {
        $customer = self::customer();
        $wanted = trim((string) config('migration.queues.probe', 'default'));

        return in_array($wanted, $customer, true) ? $wanted : $customer[0];
    }

    /** For tests: let the next misconfiguration warn again. */
    public static function forgetWarning(): void
    {
        self::$warned = false;
    }
}
