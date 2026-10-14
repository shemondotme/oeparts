<?php

namespace App\Services\Instance;

use App\Services\Cleanup\TargetRegistry;

/**
 * The settings an imported site must start with switched OFF because they arm automatic behaviour tied to the old server
 * (see `instance.origin_reset`). The Cleanup Engine's per-target switches are generated from its target registry, so a target
 * added next month is covered without anyone remembering to list it here.
 */
final class OriginResetRegistry
{
    /** @return array<string, array<int, string>> group => keys, in a stable order */
    public function settings(): array
    {
        $settings = [];

        foreach ((array) config('instance.origin_reset.settings', []) as $group => $keys) {
            $settings[(string) $group] = array_values(array_map('strval', (array) $keys));
        }

        foreach ((new TargetRegistry)->all() as $target) {
            $settings['cleanup'][] = 'auto_'.$target->key();
        }

        foreach ($settings as $group => $keys) {
            $settings[$group] = array_values(array_unique($keys));
        }

        ksort($settings);

        return $settings;
    }

    /** @return array<int, string> every "group.key" that is reset */
    public function qualified(): array
    {
        $all = [];

        foreach ($this->settings() as $group => $keys) {
            foreach ($keys as $key) {
                $all[] = $group.'.'.$key;
            }
        }

        return $all;
    }

    /** @return array<string, string> "group.key" => why it is left as imported */
    public function kept(): array
    {
        return array_map('strval', (array) config('instance.origin_reset.keep', []));
    }
}
