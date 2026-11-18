<?php

namespace App\Support;

/**
 * Settings are stored as text, and a switch that is turned off is saved as the word "false". Filament reads a
 * text value into a switch with boolval(), and boolval('false') is TRUE — so every switch saved as off came back
 * ON the next time its settings page was opened (and saving again quietly switched it back on). The stored
 * words are turned into real booleans before the form is filled.
 */
final class SettingFormState
{
    /**
     * @param  array<string, mixed>  $data  setting key => stored value
     * @return array<string, mixed>
     */
    public static function booleans(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $word = strtolower(trim($value));

                if ($word === 'true' || $word === 'false') {
                    $data[$key] = $word === 'true';
                }
            }
        }

        return $data;
    }
}
