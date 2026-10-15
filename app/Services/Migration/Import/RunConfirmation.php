<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\Reason;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;
use App\Services\Migration\Installer\ImportWizardState;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Ux\MigrationText;
use Illuminate\Support\Facades\Log;

/**
 * The runner page's confirm panel: what a person sees when the checks are done, and the one call that says "go".
 *
 * It decides nothing about whether the import may proceed. That stays with ImportManager::confirm() (the failed checks, every
 * acknowledgement, the file that must not have changed, the class of the site) and with TargetFreshness. What lives here is
 * only the part no other door shows at this point:
 *
 *  - The installer records what the person asked to bring (a preset, or ticked groups) BEFORE the new site's database has any
 *    structure, so it cannot know what those groups drag in (orders need customers: that comes from the foreign keys). Now the
 *    structure exists, the same resolver the command line and the admin page use gives the answer. Groups the package holds are
 *    ADDED and shown, and the person must say they have read that; groups the package does not hold REFUSE the choice with a
 *    plain sentence, and nothing starts.
 *  - Every warning that needs an acknowledgement is listed, each with its own tick.
 *
 * Nothing here is secret: no value of a setting, no key, no path, no address beyond the one the person typed in step 4.
 */
final class RunConfirmation
{
    /** The sort of door whose people type REPLACE: the installer's target is new, so there is nothing to replace there. */
    private const TYPED_DOORS = ['admin', 'cli'];

    public function __construct(
        private readonly ImportStateStore $store,
        private readonly GroupRegistry $registry,
        private readonly MigrationText $text,
    ) {}

    /** Is this run waiting for exactly this: a person to look at the checks and say go? */
    public function waiting(ImportState $state): bool
    {
        return $state->kind === 'import'
            && $state->status === ImportStatus::AwaitingConfirmation
            && $state->flag('preflight.done')
            && ! $state->flag('confirmed');
    }

    /**
     * What the panel shows, or null when the run is not waiting.
     *
     * @return array<string, mixed>|null
     */
    public function facts(ImportState $state): ?array
    {
        if (! $this->waiting($state)) {
            return null;
        }

        $preflight = (array) ($state->package['preflight'] ?? []);
        $selection = $this->selection($state);
        $door = (string) ($state->options['door'] ?? 'cli');
        $checks = [];
        $acks = [];
        $populated = false;

        foreach ((array) ($preflight['checks'] ?? []) as $check) {
            $ack = isset($check['ack']) && is_string($check['ack']) && $check['ack'] !== '' ? $check['ack'] : null;
            $checks[] = [
                'key' => (string) ($check['key'] ?? ''),
                'label' => (string) ($check['label'] ?? ''),
                'status' => (string) ($check['status'] ?? ''),
                'message' => (string) ($check['message'] ?? ''),
                'ack' => $ack,
            ];

            if ($ack !== null) {
                $acks[$ack] = ['key' => $ack, 'label' => (string) ($check['label'] ?? ''), 'message' => (string) ($check['message'] ?? '')];
            }

            if (($check['key'] ?? '') === 'target_state') {
                $populated = ($check['meta']['class'] ?? '') === TargetClass::Populated->value;
            }
        }

        return [
            'can_start' => ($preflight['can_proceed'] ?? false) === true && $selection['error'] === null,
            'blocked_by_checks' => ($preflight['can_proceed'] ?? false) !== true,
            'door' => $door,
            'checks' => $checks,
            'acks' => array_values($acks),
            'selection' => $selection,
            'launch' => is_string($state->options['launch'] ?? null) ? $state->options['launch'] : null,
            'url' => is_string($state->options['url'] ?? null) ? $state->options['url'] : null,
            'typed_required' => in_array($door, self::TYPED_DOORS, true),
            'populated' => $populated,
        ];
    }

    /**
     * Say "go". Returns null when the run was confirmed, or the refusal (a plain error) when the CHOICE cannot go ahead; every
     * other refusal is the engine's, thrown as a MigrationException.
     *
     * @param  array{acks?: mixed, groups_ack?: mixed, typed?: mixed, replace_populated?: mixed}  $input  what the person gave
     * @return array{error: string, title: string, message: string, fix: string}|null
     *
     * @throws MigrationException not waiting, a failed check, a missing acknowledgement, REPLACE missing, a changed file or site
     */
    public function confirm(string $runId, array $input): ?array
    {
        $state = $this->store->find($runId);

        if ($state === null || ! $this->waiting($state)) {
            throw new MigrationException(MigrationErrorCode::NotReadyToConfirm);
        }

        if (($state->options['door'] ?? null) === 'installer' && ! app(ImportWizardState::class)->databaseUnchanged()) {
            return [
                'error' => 'database_changed',
                'title' => 'The database settings changed',
                'message' => 'The database settings changed while this import was prepared. Start the import branch again.',
                'fix' => 'Cancel and begin the import branch again. Nothing was changed.',
            ];
        }

        $selection = $this->selection($state);

        if ($selection['error'] !== null) {
            return [
                'error' => 'selection_refused',
                'title' => 'This choice cannot work',
                'message' => $selection['error'],
                'fix' => 'Go back, change what to bring over, and check again. Nothing was changed.',
            ];
        }

        if ($selection['added'] !== [] && ($input['groups_ack'] ?? false) !== true) {
            return [
                'error' => 'groups_not_acknowledged',
                'title' => 'Please review the added parts',
                'message' => 'Parts were added to your choice because the rest cannot work without them. Tick that you have read this, then start.',
                'fix' => 'Tick the box under the added parts.',
            ];
        }

        $confirmation = ['replace_populated' => ($input['replace_populated'] ?? false) === true];

        if (is_string($input['typed'] ?? null)) {
            $confirmation['typed'] = $input['typed'];
        }

        // Only the groups the resolver gave are frozen into the run, never anything the request names.
        if ($selection['asked']) {
            $confirmation['options'] = ['groups' => $selection['groups']];
        }

        // The engine is built only for the call that needs it: showing the panel (status, advance, the page) never builds it here.
        app(ImportManager::class)->confirm($runId, $this->acks($input['acks'] ?? []), $confirmation);

        return null;
    }

    /**
     * The groups this run will bring, from what the person asked for in step 4 and what the package holds.
     *
     * @return array{asked: bool, groups: list<string>, labels: list<string>, added: list<array{key: string, label: string, because: list<string>}>, error: string|null}
     */
    public function selection(ImportState $state): array
    {
        $preset = $state->options['requested_preset'] ?? null;
        $groups = $state->options['requested_groups'] ?? null;
        $held = array_values(array_map('strval', (array) ($state->package['groups'] ?? [])));

        // A run that nobody asked a question for (it came from another door): the engine's own default stands.
        if (! is_array($groups) && ! (is_string($preset) && $preset !== '')) {
            return ['asked' => false, 'groups' => $held, 'labels' => $this->labels($held), 'added' => [], 'error' => null];
        }

        $presetName = is_string($preset) && $preset !== '' ? $preset : null;
        $list = array_values(array_map('strval', (array) $groups));
        $list = $list === [] ? [GroupKey::Core->value] : $list; // an empty list would mean "everything"; the choice was "just the basics"
        $choice = new ImportGroupChoice($this->registry);

        try {
            $result = $choice->resolve($held, $presetName, $presetName !== null ? null : implode(',', $list), null);
        } catch (\InvalidArgumentException $e) {
            return ['asked' => true, 'groups' => [], 'labels' => [], 'added' => [], 'error' => $this->refusalText($held, $presetName, $list, $e)];
        }

        $final = array_values(array_map('strval', $result['groups']));
        $added = array_values(array_map('strval', $result['added']));
        $reasons = $this->reasons($final, $added);

        return [
            'asked' => true,
            'groups' => $final,
            'labels' => $this->labels($final),
            'added' => array_map(fn (string $key): array => ['key' => $key, 'label' => $this->label($key), 'because' => $reasons[$key] ?? []], $added),
            'error' => null,
        ];
    }

    /**
     * The sentence for a choice that cannot work. ImportGroupChoice has already judged it (and its own sentence names the groups by
     * key); the plain version names them as people know them, from the same resolution of the foreign keys.
     *
     * @param  list<string>  $held
     * @param  list<string>  $list
     */
    private function refusalText(array $held, ?string $preset, array $list, \InvalidArgumentException $original): string
    {
        try {
            if ($preset !== null) {
                $found = Preset::tryFrom($preset);
                $wanted = $found === null ? [] : array_values(array_filter($found->groups(), static fn (GroupKey $k): bool => in_array($k->value, $held, true) || $k === GroupKey::Core));
            } else {
                $wanted = array_values(array_filter(array_map(static fn (string $name): ?GroupKey => GroupKey::tryFrom($name), $list)));
            }

            if ($wanted !== []) {
                $final = array_map(static fn (GroupKey $k): string => $k->value, (new DependencyResolver($this->registry, new FkGraph))->resolve($wanted)->groups);
                $missing = array_values(array_diff($final, [GroupKey::Core->value, ...$held]));

                if ($missing !== []) {
                    $names = implode(', ', array_map(fn (string $key): string => $this->label($key), $missing));
                    $them = count($missing) === 1 ? 'it' : 'them';

                    return "What you chose needs {$names}, and this package does not hold {$them}. Leave out what depends on {$them}, or export the site again with {$them} included.";
                }
            }
        } catch (\Throwable $e) {
            Log::channel('migration')->warning('The plain sentence for a refused choice could not be built: '.$e::class);
        }

        return $original->getMessage();
    }

    /**
     * Why each added group is there: the groups whose tables point at it.
     *
     * @param  list<string>  $final
     * @param  list<string>  $added
     * @return array<string, list<string>> added group => labels of the groups that need it
     */
    private function reasons(array $final, array $added): array
    {
        if ($added === []) {
            return [];
        }

        try {
            $requested = array_values(array_filter(array_map(static fn (string $key): ?GroupKey => GroupKey::tryFrom($key), array_diff($final, $added))));
            $resolution = (new DependencyResolver($this->registry, new FkGraph))->resolve($requested);
            $because = [];

            foreach ($added as $key) {
                $names = array_map(fn (Reason $r): string => $this->label($r->becauseOf->value), $resolution->added[$key] ?? []);
                $because[$key] = array_values(array_unique($names));
            }

            return $because;
        } catch (\Throwable $e) {
            // The explanation is a courtesy; the groups themselves are already decided. Say so in the log rather than hide it.
            Log::channel('migration')->warning('The reasons for the added parts could not be worked out: '.$e::class);

            return [];
        }
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function labels(array $keys): array
    {
        return array_map(fn (string $key): string => $this->label($key), $keys);
    }

    private function label(string $key): string
    {
        return $this->text->get('group.'.$key.'.label', [], true);
    }

    /**
     * @return list<string>
     */
    private function acks(mixed $given): array
    {
        $acks = [];

        foreach (array_slice(is_array($given) ? $given : [], 0, 64) as $ack) {
            if (is_string($ack) && $ack !== '' && strlen($ack) <= 64) {
                $acks[] = $ack;
            }
        }

        return array_values(array_unique($acks));
    }
}
