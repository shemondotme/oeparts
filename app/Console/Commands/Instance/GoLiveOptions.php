<?php

namespace App\Console\Commands\Instance;

/** Reads the options the two launch commands share. */
final class GoLiveOptions
{
    /**
     * "key:reason" strings into key => reason.
     *
     * @param  array<int, mixed>  $given
     * @return array<string, string>|null null when one of them is not in that form
     */
    public static function waivers(array $given): ?array
    {
        $waivers = [];

        foreach ($given as $item) {
            [$key, $reason] = array_pad(explode(':', (string) $item, 2), 2, '');

            if (trim($key) === '' || trim($reason) === '') {
                return null;
            }

            $waivers[trim($key)] = trim($reason);
        }

        return $waivers;
    }
}
