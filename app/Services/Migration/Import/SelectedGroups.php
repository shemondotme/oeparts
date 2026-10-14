<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Groups\GroupKey;

/** The groups an import loads: what the person chose (or, failing that, what the package holds). Core is always one of them. */
final class SelectedGroups
{
    /** @return array<int, GroupKey> */
    public static function of(ImportState $state): array
    {
        $chosen = (array) ($state->options['groups'] ?? $state->package['groups'] ?? []);
        $groups = [GroupKey::Core];

        foreach ($chosen as $value) {
            $key = GroupKey::tryFrom((string) $value);

            if ($key !== null && $key !== GroupKey::System && ! in_array($key, $groups, true)) {
                $groups[] = $key;
            }
        }

        return $groups;
    }

    public static function includes(ImportState $state, GroupKey $group): bool
    {
        return in_array($group, self::of($state), true);
    }
}
