<?php

namespace Tests\Support;

use App\Security\PassphraseThrottle;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Ux\PackageUnlocker;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * For tests of the installer's import branch that need a REAL package and a real unlock: the real writer with a cheap key
 * derivation, and everything the unlock touches (wrong-passphrase counters, key escrow, wizard state, inbox) in a temporary
 * folder. Nothing here can reach the developer's own storage.
 *
 * The class using it must extend a TestCase, call prepareOpenedPackageTest() from setUp() and cleanUpOpenedPackageTest() from
 * tearDown().
 */
trait OpensInstallerPackages
{
    use BuildsPackages;
    use DrivesInstallerImport;

    private int $clock = 1_800_000_000;

    private KeyEscrow $escrow;

    private PassphraseThrottle $throttle;

    private function prepareOpenedPackageTest(): void
    {
        $this->prepareInstallerImportTest();
        $this->workDir = $this->inboxDir();

        $kdf = $this->weakKdf();
        $this->app->instance(Kdf::class, $kdf);

        $this->throttle = new PassphraseThrottle($this->tmp.DIRECTORY_SEPARATOR.'throttle', 5, 60, 900, 86400, fn (): int => $this->clock);
        $this->escrow = new KeyEscrow($this->tmp.DIRECTORY_SEPARATOR.'escrow', (string) config('app.key'));

        $this->app->instance(PackageUnlocker::class, new PackageUnlocker($this->throttle, $kdf));
        $this->app->instance(KeyEscrow::class, $this->escrow);
    }

    private function cleanUpOpenedPackageTest(): void
    {
        $this->cleanUpInstallerImportTest();
    }

    /**
     * A real, finished package that has been in the inbox long enough to count as fully uploaded.
     *
     * @param  array<string, mixed>  $meta  merged over the default meta (source, created_at, app_version, export)
     */
    private function buildPackage(string $name = 'shop.oeparts', array $meta = []): string
    {
        $writer = $this->newWriter($name, array_replace_recursive([
            'source' => ['domain' => 'old.example'],
            'created_at' => '2026-10-01T10:00:00+00:00',
            'app_version' => '2.1.0',
            'export' => ['groups' => ['core', 'catalog', 'media'], 'preset' => 'full_live', 'anonymised' => false, 'secrets_included' => false],
        ], $meta));
        $writer->addEntryFromString('report/small.json', 'report', '{"ok":true}', ['group' => 'settings']);
        $writer->seal();

        $path = $this->inboxDir().DIRECTORY_SEPARATOR.$name;
        touch($path, time() - 600);

        return $path;
    }

    /** A browser that started the branch and finished the database step. */
    private function ownerAtPackageStep(): static
    {
        $nonce = $this->startBranch();

        return $this->asBrowser($nonce)->withSession(['installer.db_configured' => true]);
    }

    private function openPackage(string $name = 'shop.oeparts', ?string $passphrase = null)
    {
        return $this->post('/install/import/package', ['package' => $name, 'passphrase' => $passphrase ?? $this->passphrase]);
    }
}
