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
use App\Services\Migration\Package\DerivedKeys;
use App\Services\Migration\Ux\PackageUnlocker;
use Illuminate\Support\Facades\Log;

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
    /** The only escrow ids a run may adopt: the installer's (see InstallerPackageUnlock). */
    private const ADOPTABLE_ID = '/^wiz-[0-9a-f]{24}$/';

    /** Set once the hand-over was looked at and not used: the same answer would come on every poll, and be logged on every poll. */
    private const ADOPT_REFUSED = 'adopt.refused';

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

        $adoptedFrom = null;

        if ($context->passphrase !== null) {
            $unlocked = $this->unlocker->unlock($path, $context->passphrase, $context->clientKey);
        } else {
            // The installer opened the package in an earlier step and left only its keys in the escrow: take them over.
            [$unlocked, $adoptedFrom] = $located ? [null, null] : $this->adopt($state, $path, $context);
        }

        if ($unlocked === null) {
            return ImportStepResult::needsPassphrase($located ? 'The key for this package has expired: type the passphrase again.' : 'Type the passphrase of the package.');
        }

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

        // The run holds its own copy now; the installer's entry has done its job.
        if ($adoptedFrom !== null) {
            $context->escrow->delete($adoptedFrom);
        }

        return ImportStepResult::done('Package opened.');
    }

    /**
     * The installer's hand-over: a run may name an escrow entry made by the installer's package step (`adopt_escrow`, set by the
     * installer's own code when it starts the run, never by anything a request can send). The keys in it are only taken if they
     * open THIS file, checked against the file itself (PackageUnlocker::adopt), so keys of another package cannot be adopted.
     *
     * Anything wrong (no such option, a name that is not the installer's, no entry, an expired entry, keys that do not open the
     * file) gives the person the same quiet answer, "ask for the passphrase", and says nothing about which case it was. The
     * operator is told, though: refuse() writes the reason, as a code, to the migration log. The answer cannot change between
     * two polls of the same run, so it is given (and logged) once and remembered in the run's flags.
     *
     * @return array{0: array{keys: DerivedKeys, package_id: string, fingerprint: string}|null, 1: string|null} the opened keys and the id to delete
     */
    private function adopt(ImportState $state, string $path, ImportContext $context): array
    {
        if ($state->flag(self::ADOPT_REFUSED)) {
            return [null, null];
        }

        $id = $state->options['adopt_escrow'] ?? null;

        if ($id === null) {
            return $this->refuse($state, 'no_option');
        }

        if (! is_string($id) || preg_match(self::ADOPTABLE_ID, $id) !== 1) {
            return $this->refuse($state, 'bad_id', ['id_shape' => $this->shapeOf($id)]);
        }

        try {
            $entry = $context->escrow->get($id);
        } catch (\Throwable $e) {
            return $this->refuse($state, 'exception', $this->describe($e));
        }

        if ($entry === null) {
            return $this->refuse($state, 'no_entry');
        }

        try {
            $unlocked = $this->unlocker->adopt($path, $entry->keys);
        } catch (MigrationException $e) {
            // The reader's key check fails with "package changed": the keys were made for another file, or for this one before it was altered.
            return $e->errorCode === MigrationErrorCode::PackageChanged
                ? $this->refuse($state, 'keys_do_not_open_file')
                : $this->refuse($state, 'exception', $this->describe($e));
        } catch (\Throwable $e) {
            return $this->refuse($state, 'exception', $this->describe($e));
        }

        if ($unlocked['package_id'] !== $entry->packageIdHex) {
            return $this->refuse($state, 'package_id_mismatch');
        }

        return [$unlocked, $id];
    }

    /**
     * The hand-over is not used. The log gets a reason CODE and the run's own id, nothing else: never the escrow id (beyond what
     * shapeOf() says about a malformed one), a key, a passphrase, a path or an exception message, which can carry any of them.
     *
     * @param  array<string, scalar|null>  $detail
     * @return array{0: null, 1: null}
     */
    private function refuse(ImportState $state, string $reason, array $detail = []): array
    {
        $state->setFlag(self::ADOPT_REFUSED);

        Log::channel('migration')->warning("Import {$state->id}: the keys the installer unlocked were not used ({$reason}); the passphrase is asked for instead.", ['reason' => $reason] + $detail);

        return [null, null];
    }

    /** What kind of thing an unusable option was, without echoing it: its type, and for a string its length. */
    private function shapeOf(mixed $value): string
    {
        return is_string($value) ? 'string:'.strlen($value) : get_debug_type($value);
    }

    /** @return array<string, string> the class of an exception (and the catalogue code of a migration one), never its message */
    private function describe(\Throwable $e): array
    {
        return ['exception' => $e::class] + ($e instanceof MigrationException ? ['code' => $e->errorCode->value] : []);
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
