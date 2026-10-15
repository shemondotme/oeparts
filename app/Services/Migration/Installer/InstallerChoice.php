<?php

namespace App\Services\Migration\Installer;

use App\Services\Instance\CanonicalHostPolicy;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Ux\MigrationText;
use Illuminate\Http\Request;

/**
 * Step 4 of the installer's import branch: what to bring over, whether this is the real shop, and where it will live.
 *
 * It offers only what the open package holds and checks what came back from the browser; it never trusts a field. What it
 * does NOT do is work out which other groups a choice drags in (orders need customers): that comes from the foreign keys of
 * the database, and at this step the new site's database is still empty (its structure is built by the run itself, after the
 * package is checked). The closure is applied when the run asks for its confirmation, where the real structure exists.
 */
final class InstallerChoice
{
    public const CUSTOM = 'custom';

    public function __construct(
        private readonly GroupRegistry $registry,
        private readonly MigrationText $text,
        private readonly InstallerSiteAddress $address,
    ) {}

    /**
     * The presets the package can honour in full: every group the preset names (core aside) is in the package.
     *
     * @param  array<int, string>  $held  group keys the package carries
     * @return array<int, array{key: string, label: string, description: string, groups: array<int, string>}>
     */
    public function presets(array $held): array
    {
        $offered = [];

        foreach (Preset::cases() as $preset) {
            $names = array_map(static fn (GroupKey $k): string => $k->value, $preset->groups());
            $need = array_values(array_diff($names, [GroupKey::Core->value]));

            if ($need === [] || array_diff($need, $held) !== []) {
                continue;
            }

            $offered[] = [
                'key' => $preset->value,
                'label' => $this->text->get('preset.'.$preset->value.'.label', [], true),
                'description' => $this->text->get('preset.'.$preset->value.'.description', [], true),
                'groups' => $names,
            ];
        }

        return $offered;
    }

    /**
     * Every group the package holds that a person can choose, in the order people see them. Core is always there and cannot be unticked.
     *
     * @param  array<int, string>  $held
     * @return array<int, array{key: string, label: string, description: string, mandatory: bool}>
     */
    public function groups(array $held): array
    {
        $groups = [];

        foreach ($this->registry->selectable() as $group) {
            $key = $group->key->value;

            if (! $group->isMandatory() && ! in_array($key, $held, true)) {
                continue;
            }

            $groups[] = [
                'key' => $key,
                'label' => $this->text->get('group.'.$key.'.label', [], true),
                'description' => $this->text->get('group.'.$key.'.description', [], true),
                'mandatory' => $group->isMandatory(),
            ];
        }

        return $groups;
    }

    /**
     * Check what the form sent against what the package holds.
     *
     * @param  array<int, string>  $held
     * @param  array<string, mixed>  $input  selection (preset:<name> or custom), groups[], launch, site_url
     * @return array{choices: array<string, mixed>|null, errors: array<string, string>}
     */
    public function check(array $held, array $input, Request $request): array
    {
        $errors = [];
        $choices = [];

        $selection = (string) ($input['selection'] ?? '');
        $offered = array_column($this->presets($held), 'groups', 'key');

        if (str_starts_with($selection, 'preset:') && isset($offered[substr($selection, 7)])) {
            $choices['selection'] = $selection;
            $choices['preset'] = substr($selection, 7);
            $choices['groups'] = $offered[$choices['preset']];
        } elseif ($selection === self::CUSTOM) {
            $picked = $this->pickedGroups($held, $input['groups'] ?? []);

            if ($picked === null) {
                $errors['groups'] = 'One of the parts you ticked is not in this package.';
            } else {
                $choices['selection'] = self::CUSTOM;
                $choices['preset'] = null;
                $choices['groups'] = $picked;
            }
        } else {
            $errors['selection'] = 'Choose what to bring over.';
        }

        $launch = (string) ($input['launch'] ?? '');

        if (! in_array($launch, [CanonicalHostPolicy::LIVE, CanonicalHostPolicy::STAGING], true)) {
            $errors['launch'] = 'Say whether this is your real, live site. There is no default.';
        } else {
            $choices['launch'] = $launch;
        }

        try {
            $choices['url'] = $this->address->check((string) ($input['site_url'] ?? ''), $request);
        } catch (MigrationException $e) {
            $errors['site_url'] = $this->text->forException($e, true)['message'].' '.$this->text->forException($e, true)['fix'];
        }

        return ['choices' => $errors === [] ? $choices : null, 'errors' => $errors];
    }

    /**
     * Core plus the groups ticked, each of them held by the package. Unknown names and groups the package lacks refuse the whole choice.
     *
     * @param  array<int, string>  $held
     * @return array<int, string>|null null when something ticked is not available
     */
    private function pickedGroups(array $held, mixed $ticked): ?array
    {
        $picked = [GroupKey::Core->value];

        foreach ((array) $ticked as $name) {
            $name = (string) $name;
            $key = GroupKey::tryFrom($name);

            if ($key === null || $key === GroupKey::System || ! in_array($name, $held, true)) {
                return null;
            }

            if (! in_array($name, $picked, true)) {
                $picked[] = $name;
            }
        }

        return $picked;
    }
}
