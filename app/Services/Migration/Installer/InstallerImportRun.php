<?php

namespace App\Services\Migration\Installer;

use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Support\Facades\Log;

/**
 * Step 5 of the installer's import branch: the moment the branch turns into a run.
 *
 * Everything the run needs from the installer is decided HERE, by installer code, and handed to the engine as the run's options:
 * which unlocked keys to take over (`adopt_escrow`, only this class and the wizard's own unlock ever name one), the address, live
 * or test, and what the person asked to bring. Nothing in a request can reach those options; the request only presses the button.
 *
 * The choice of groups is recorded as what the person ASKED for (`requested_preset`, `requested_groups`), not as the final list:
 * what a choice drags in comes from the foreign keys of the new site's database, which has no structure yet. The runner's confirm
 * panel (RunConfirmation) resolves it once the structure exists and asks the person to confirm anything that was added.
 */
final class InstallerImportRun
{
    public function __construct(
        private readonly ImportWizardState $wizard,
        private readonly ImportStateStore $store,
    ) {}

    /**
     * Are the saved choices a whole set: something to bring, live or test answered, and an address?
     *
     * @param  array<string, mixed>  $choices
     */
    public function choicesComplete(array $choices): bool
    {
        return in_array($choices['launch'] ?? null, ['live', 'staging'], true)
            && is_string($choices['url'] ?? null) && $choices['url'] !== ''
            && is_array($choices['groups'] ?? null) && $choices['groups'] !== [];
    }

    /**
     * Start the run: open the package with the keys the installer already holds, and hand back the runner's address data.
     *
     * @param  array{package: string, escrow_id: string}  $open  the open package (InstallerPackageUnlock::current())
     * @param  array<string, mixed>  $choices  the saved choices
     * @return array{run_id: string, token: string} the token is shown ONCE (the server keeps only its hash)
     *
     * @throws MigrationException the package is gone or changed, or the keys could not be taken over (the person opens it again)
     */
    public function start(array $open, array $choices, string $ip): array
    {
        $this->discardEarlierCheck();

        $started = $this->manager()->start(
            ['inbox' => $open['package']],
            null,
            ['door' => 'installer', 'ip' => $ip],
            'import',
            'installer:'.$ip,
            [
                'door' => 'installer',
                'adopt_escrow' => $open['escrow_id'],
                'launch' => $choices['launch'],
                'url' => $choices['url'],
                'requested_preset' => is_string($choices['preset'] ?? null) ? $choices['preset'] : null,
                'requested_groups' => array_values(array_map('strval', (array) $choices['groups'])),
            ],
        );

        $state = $started['state'];

        // The keys could not be taken over (they expired, or belong to another file): a run that cannot read its package is no run.
        if (! $state->flag('locate.done') || $state->flag('needs_passphrase') || isset($started['progress']['input_error'])) {
            $this->manager()->discard($state->id);

            throw new MigrationException(MigrationErrorCode::KeyEscrowLost);
        }

        $this->wizard->change(['run' => $state->id]);

        return ['run_id' => $state->id, 'token' => $started['token']];
    }

    /**
     * The engine is built only when a run is started or discarded, never when the installer's pages are: building it reaches into
     * the database and the application's services, and the first pages of the installer run before there is a database at all.
     */
    private function manager(): ImportManager
    {
        return app(ImportManager::class);
    }

    /** Pressing "check" twice, or going back and pressing it again, must not leave an unconfirmed run behind. */
    private function discardEarlierCheck(): void
    {
        $earlier = $this->wizard->read()['run'] ?? null;

        if (! is_string($earlier) || ! ImportStateStore::validId($earlier)) {
            return;
        }

        try {
            $state = $this->store->find($earlier);

            if ($state !== null && ! $state->status->isTerminal() && ! $state->flag('confirmed') && ! $state->isDestructive()) {
                $this->manager()->discard($earlier);
            }
        } catch (\Throwable $e) {
            Log::channel('migration')->warning("The earlier check {$earlier} of the installer could not be discarded: ".$e::class);
        }
    }
}
