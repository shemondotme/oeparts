<?php

namespace App\Services\Migration\Installer;

use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\DerivedKeys;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageReader;
use App\Services\Migration\Ux\PackageUnlocker;
use Illuminate\Http\Request;

/**
 * Step 3 of the installer's import branch: choose a package from the inbox and open it with its passphrase.
 *
 * What it does with the result is the point. The passphrase lives for the length of this call and is never stored; the keys it
 * derives go to the KeyEscrow, wrapped with the site key, under an id that belongs to this browser's branch. The wizard file
 * keeps that id and a few facts the person is shown (where the package came from, when it was made, what it holds) and nothing
 * secret. Later steps, and the run itself, use the escrow entry; nobody asks for the passphrase twice.
 *
 * Wrong passphrases go through the same throttle every door uses (per package and per client), and a connection that is not
 * secure is refused before any key is derived.
 */
final class InstallerPackageUnlock
{
    public function __construct(
        private readonly ImportWizardState $wizard,
        private readonly InstallerInbox $installerInbox,
        private readonly ImportInbox $inbox,
        private readonly PackageUnlocker $unlocker,
        private readonly KeyEscrow $escrow,
        private readonly PassphraseTransportGuard $transport,
        private readonly Kdf $kdf,
    ) {}

    /**
     * Open the named package. On success the keys are in the escrow and the wizard remembers which package is open.
     *
     * @return array<string, mixed> the summary shown to the person
     *
     * @throws MigrationException a refused connection, a file that is missing, still arriving or damaged, a wrong or locked passphrase
     */
    public function open(Request $request, string $name, string $passphrase): array
    {
        $this->transport->assertAllows($request);

        if (! ImportInbox::isPackageName($name) || ! $this->installerInbox->isStable($name)) {
            // A name that is not a finished file in the inbox is the same answer whether it is missing or still being copied in.
            throw new MigrationException($this->exists($name) ? MigrationErrorCode::PackageIncomplete : MigrationErrorCode::PackageNotFound, ['path' => basename($name)]);
        }

        $path = $this->inbox->resolve($name);
        $unlocked = $this->unlocker->unlock($path, $passphrase, 'installer:'.(string) $request->ip());

        $escrowId = $this->escrowId();
        $this->escrow->put($escrowId, $unlocked['package_id'], $unlocked['keys']);

        $summary = $this->summaryOf($path, $unlocked['keys']);

        // A different package than the one opened before: whatever was chosen for that one means nothing here.
        $previous = $this->wizard->read()['unlock']['package'] ?? null;

        $this->wizard->change([
            'package' => $name,
            'unlock' => [
                'package' => $name,
                'escrow_id' => $escrowId,
                'package_id' => $unlocked['package_id'],
                'fingerprint' => $unlocked['fingerprint'],
                'summary' => $summary,
            ],
            'choices' => $previous === $name ? $this->wizard->choices() : [],
        ]);

        return $summary;
    }

    /**
     * The package that is open for this browser's branch, if its keys are still in the escrow.
     *
     * @return array{package: string, escrow_id: string, package_id: string, fingerprint: string, summary: array<string, mixed>}|null
     */
    public function current(): ?array
    {
        $unlock = $this->wizard->read()['unlock'] ?? null;

        if (! is_array($unlock) || ! isset($unlock['escrow_id'], $unlock['package'], $unlock['package_id'], $unlock['fingerprint'])) {
            return null;
        }

        try {
            $entry = $this->escrow->get((string) $unlock['escrow_id']);
        } catch (MigrationException) {
            $entry = null;
        }

        if ($entry === null) {
            // The keys expired (they are kept for a day at most): the person types the passphrase again.
            $this->wizard->change(['unlock' => null]);

            return null;
        }

        return [
            'package' => (string) $unlock['package'],
            'escrow_id' => (string) $unlock['escrow_id'],
            'package_id' => (string) $unlock['package_id'],
            'fingerprint' => (string) $unlock['fingerprint'],
            'summary' => is_array($unlock['summary'] ?? null) ? $unlock['summary'] : [],
        ];
    }

    /** Close the open package: its keys leave the escrow and the wizard forgets it. */
    public function forget(): void
    {
        $this->release();
        $this->wizard->change(['package' => null, 'unlock' => null, 'choices' => []]);
    }

    /** Remove this branch's keys from the escrow (the branch ended, or another package was chosen). Safe when there are none. */
    public function release(): void
    {
        $unlock = $this->wizard->read()['unlock'] ?? null;

        if (is_array($unlock) && is_string($unlock['escrow_id'] ?? null)) {
            try {
                $this->escrow->delete($unlock['escrow_id']);
            } catch (\Throwable) {
                // The janitor removes anything past its lifetime; a leftover file is not a reason to stop leaving the branch.
            }
        }
    }

    /** The escrow id of this branch. Derived from the owner's stored hash, so it is stable and valid as a file name. */
    private function escrowId(): string
    {
        return 'wiz-'.substr((string) ($this->wizard->read()['nonce_hash'] ?? bin2hex(random_bytes(12))), 0, 24);
    }

    private function exists(string $name): bool
    {
        try {
            $this->inbox->resolve($name);

            return true;
        } catch (MigrationException) {
            return false;
        }
    }

    /**
     * What the package says about itself (it was authenticated when it opened), reduced to what a person decides on.
     *
     * @return array<string, mixed>
     */
    private function summaryOf(string $path, DerivedKeys $keys): array
    {
        try {
            $reader = PackageReader::openWithKeys($path, $keys, $this->kdf);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        }

        try {
            $meta = $reader->meta();
            $export = (array) ($meta['export'] ?? []);

            return [
                'source_domain' => is_string($meta['source']['domain'] ?? null) ? $meta['source']['domain'] : null,
                'created_at' => is_string($meta['created_at'] ?? null) ? $meta['created_at'] : null,
                'app_version' => is_string($meta['app_version'] ?? null) ? $meta['app_version'] : null,
                'size' => $reader->fileSize(),
                'groups' => array_values(array_filter((array) ($export['groups'] ?? []), 'is_string')),
                'preset' => is_string($export['preset'] ?? null) ? $export['preset'] : null,
                'anonymised' => (bool) ($export['anonymised'] ?? false),
                'secrets_included' => (bool) ($export['secrets_included'] ?? false),
            ];
        } finally {
            $reader->close();
        }
    }
}
