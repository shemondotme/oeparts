<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;

/**
 * Which of the groups in a package the person wants brought over: a ready-made preset, an explicit list, or everything, minus
 * what they leave out. Core always comes. The choice is checked against the package (a group it does not hold cannot be brought)
 * and against what the chosen groups cannot do without (orders need customers: if the package holds them they are added and the
 * person is told; if it does not, the choice is refused, because the orders would point at nobody).
 */
final class ImportGroupChoice
{
    public function __construct(private readonly GroupRegistry $registry = new GroupRegistry) {}

    /**
     * @param  array<int, string>  $inPackage  group keys the package carries
     * @return array{groups: array<int, string>, added: array<int, string>}
     *
     * @throws \InvalidArgumentException an unknown preset or group, a group the package does not hold, or a missing dependency
     */
    public function resolve(array $inPackage, ?string $preset, ?string $groups, ?string $without): array
    {
        $held = array_values(array_unique([GroupKey::Core->value, ...$inPackage]));
        $wanted = $this->wanted($held, $preset, $groups);

        foreach ($this->parseGroups($without) as $drop) {
            $wanted = array_values(array_filter($wanted, static fn (GroupKey $k): bool => $k !== $drop || $k === GroupKey::Core));
        }

        $resolution = (new DependencyResolver($this->registry, new FkGraph))->resolve($wanted);
        $final = array_map(static fn (GroupKey $k): string => $k->value, $resolution->groups);
        $missing = array_values(array_diff($final, $held));

        if ($missing !== []) {
            throw new \InvalidArgumentException('The groups you chose need '.implode(', ', $missing).', which this package does not hold. Leave out what depends on them, or export again with them included.');
        }

        $requested = array_map(static fn (GroupKey $k): string => $k->value, $wanted);

        return ['groups' => $final, 'added' => array_values(array_diff($final, $requested))];
    }

    /**
     * @param  array<int, string>  $held
     * @return array<int, GroupKey>
     */
    private function wanted(array $held, ?string $preset, ?string $groups): array
    {
        if ($preset !== null && $preset !== '' && $groups !== null && $groups !== '') {
            throw new \InvalidArgumentException('Give either --preset or --groups, not both.');
        }

        if ($preset !== null && $preset !== '') {
            $found = Preset::tryFrom($preset) ?? throw new \InvalidArgumentException("There is no preset \"{$preset}\" (full_live, staging, new_store_same_catalog, catalog_only).");

            // A preset names what it WOULD bring; only what the package holds can come.
            return array_values(array_filter($found->groups(), static fn (GroupKey $k): bool => in_array($k->value, $held, true) || $k === GroupKey::Core));
        }

        if ($groups !== null && $groups !== '') {
            $chosen = $this->parseGroups($groups);

            foreach ($chosen as $key) {
                if (! in_array($key->value, $held, true)) {
                    throw new \InvalidArgumentException("This package does not hold the group \"{$key->value}\".");
                }
            }

            return array_values(array_unique([GroupKey::Core, ...$chosen], SORT_REGULAR));
        }

        return array_values(array_map(static fn (string $k): GroupKey => GroupKey::from($k), array_filter($held, static fn (string $k): bool => GroupKey::tryFrom($k) !== null && GroupKey::from($k) !== GroupKey::System)));
    }

    /** @return array<int, GroupKey> */
    private function parseGroups(?string $list): array
    {
        $keys = [];

        foreach (array_filter(array_map('trim', explode(',', (string) $list))) as $name) {
            $key = GroupKey::tryFrom($name);

            if ($key === null || $key === GroupKey::System) {
                throw new \InvalidArgumentException("There is no group \"{$name}\".");
            }

            $keys[] = $key;
        }

        return $keys;
    }
}
