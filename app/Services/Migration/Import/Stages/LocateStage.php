<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\PackageIdentity;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Ux\PackageUnlocker;

/**
 * Stage 1. Finds the package, proves the passphrase, puts the keys in the escrow and pins the file's identity.
 *
 * It changes nothing on the site. The passphrase is used here and nowhere else: it is held in memory for the call, the
 * keys derived from it go to the escrow wrapped with the site key (so later requests do not pay for the derivation
 * again and nothing secret is ever in the state file), and the identity of the file (size, time, header, trailer,
 * fingerprint) is recorded so that a file swapped, touched or appended to afterwards stops the run while it is harmless.
 *
 * Resuming: a run that already located its package only needs its keys to still be in the escrow; if they expired a
 * person types the passphrase again, and it must open the SAME package (the fingerprint is compared).
 */
final class LocateStage implements ImportStage
{
    public function __construct(
        private readonly ImportInbox $inbox,
        private readonly PackageUnlocker $unlocker,
    ) {}

    public function key(): string
    {
        return 'locate';
    }

    public function destructive(): bool
    {
        return false;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        $path = $this->path($state);
        $escrowId = ImportContext::escrowId($state->id);
        $located = $state->flag('locate.done');

        if ($located) {
            PackageIdentity::assertUnchanged($path, (array) ($state->package['identity'] ?? []));

            if ($context->escrow->get($escrowId) !== null) {
                return ImportStepResult::done('Package found.');
            }
        }

        if ($context->passphrase === null) {
            return ImportStepResult::needsPassphrase($located ? 'The key for this package has expired: type the passphrase again.' : 'Type the passphrase of the package.');
        }

        $unlocked = $this->unlocker->unlock($path, $context->passphrase, $context->clientKey);

        if ($located && ($state->package['identity']['fingerprint'] ?? null) !== $unlocked['fingerprint']) {
            throw new MigrationException(MigrationErrorCode::PackageChanged, ['what' => 'fingerprint']);
        }

        // The keys first, the flag after: a crash between the two simply does this again and replaces the same entry.
        $context->escrow->put($escrowId, $unlocked['package_id'], $unlocked['keys']);

        if (! $located) {
            $state->package['identity'] = PackageIdentity::capture($path, $unlocked['fingerprint']);
            $state->package['package_id'] = $unlocked['package_id'];
            $state->package['fingerprint'] = $unlocked['fingerprint'];
            $state->escrowId = $escrowId;
            $state->setFlag('locate.done');
        }

        return ImportStepResult::done('Package opened.');
    }

    /** The package's absolute path, re-checked every time: a web run may only ever use a file that is still in the inbox. */
    private function path(ImportState $state): string
    {
        $path = (string) ($state->package['path'] ?? '');

        if ($path === '') {
            throw new MigrationException(MigrationErrorCode::PackageNotFound);
        }

        if (($state->package['origin'] ?? 'cli') === 'inbox') {
            $resolved = $this->inbox->resolve(basename($path));

            if ($resolved !== $path) {
                throw new MigrationException(MigrationErrorCode::PackageNotFound, ['path' => basename($path)]);
            }

            return $resolved;
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new MigrationException(MigrationErrorCode::PackageNotFound, ['path' => basename($path)]);
        }

        return $path;
    }
}
