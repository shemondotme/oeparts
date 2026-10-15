<?php

namespace App\Filament\Widgets\Migration;

use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use Closure;
use Filament\Forms\Components\CheckboxList;

/**
 * The list of parts of a shop a person ticks, built in ONE place for the Export and the Import wizards so the two can never
 * word or order a part differently. The export offers every part a site can have; the import offers only the parts the opened
 * package holds (`$only`). "Site basics" (core) is always part of it and cannot be unticked.
 */
final class GroupPickerSchema
{
    /**
     * @param  Closure(): array<int, string>|null  $only  the part keys to offer; null offers all of them
     */
    public static function make(string $name = 'groups', ?Closure $only = null): CheckboxList
    {
        return CheckboxList::make($name)
            ->options(fn (): array => self::options($only === null ? null : $only()))
            ->descriptions(fn (): array => self::descriptions($only === null ? null : $only()))
            ->disableOptionWhen(static fn (string $value): bool => $value === GroupKey::Core->value)
            // a ticked option that is disabled in the page would otherwise fail the "in" rule, which only lists enabled options
            ->in(fn (): array => array_keys(self::options($only === null ? null : $only())));
    }

    /**
     * @param  array<int, string>|null  $only
     * @return array<string, string> part key => plain label, in the registry's order
     */
    public static function options(?array $only = null): array
    {
        $options = [];

        foreach (self::keys($only) as $key) {
            $options[$key] = __('migration.group.'.$key.'.label');
        }

        return $options;
    }

    /**
     * @param  array<int, string>|null  $only
     * @return array<string, string> part key => one plain sentence
     */
    public static function descriptions(?array $only = null): array
    {
        $descriptions = [];

        foreach (self::keys($only) as $key) {
            $descriptions[$key] = __('migration.group.'.$key.'.description');
        }

        return $descriptions;
    }

    /**
     * @param  array<int, string>|null  $only
     * @return list<string>
     */
    private static function keys(?array $only): array
    {
        $all = array_keys(app(GroupRegistry::class)->selectable());

        return array_values($only === null ? $all : array_filter($all, static fn (string $key): bool => $key === GroupKey::Core->value || in_array($key, $only, true)));
    }
}
