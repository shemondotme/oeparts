<?php

namespace Tests\Support;

use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Installer\ImportWizardState;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * What every test of the installer's import branch needs, and the one rule behind it: nothing here may touch the developer's
 * own storage. The wizard state file and the package inbox live in a temporary folder bound into the container, and the
 * developer's installed.lock (the installer routes refuse a site that has one) is put back afterwards.
 *
 * The class using it must extend a TestCase and call prepareInstallerImportTest() from setUp() and
 * cleanUpInstallerImportTest() from tearDown().
 */
trait DrivesInstallerImport
{
    private string $tmp = '';

    private ?string $realLock = null;

    private function prepareInstallerImportTest(): void
    {
        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-wizard-'.bin2hex(random_bytes(6));
        mkdir($this->tmp.DIRECTORY_SEPARATOR.'inbox', 0700, true);

        $this->app->instance(ImportWizardState::class, new ImportWizardState($this->tmp.DIRECTORY_SEPARATOR.'import-wizard.json'));
        $this->app->instance(ImportInbox::class, new ImportInbox($this->tmp.DIRECTORY_SEPARATOR.'inbox'));

        $lock = storage_path('installed.lock');
        $this->realLock = is_file($lock) ? (string) file_get_contents($lock) : null;
        File::delete($lock);
    }

    private function cleanUpInstallerImportTest(): void
    {
        if ($this->realLock !== null) {
            File::put(storage_path('installed.lock'), $this->realLock);
        } else {
            File::delete(storage_path('installed.lock'));
        }

        File::deleteDirectory($this->tmp);
    }

    private function inboxDir(): string
    {
        return $this->tmp.DIRECTORY_SEPARATOR.'inbox';
    }

    private function wizard(): ImportWizardState
    {
        return app(ImportWizardState::class);
    }

    /** Start the branch as a new browser and return its cookie value. */
    private function startBranch(string $ip = '10.0.0.1'): string
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/install/import');
        $response->assertRedirect(route('installer.database'));

        $cookie = $this->cookieOf($response);
        $this->assertInstanceOf(Cookie::class, $cookie);

        return $cookie->getValue();
    }

    private function cookieOf($response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === ImportWizardState::COOKIE) {
                return $cookie;
            }
        }

        return null;
    }

    /** Requests from here on carry the owner's cookie (a plain value: the cookie is not encrypted). */
    private function asBrowser(string $nonce): static
    {
        $this->unencryptedCookies = [];

        return $this->withUnencryptedCookie(ImportWizardState::COOKIE, $nonce);
    }

    /** Requests from here on carry no cookie of the branch. */
    private function aStranger(): static
    {
        $this->unencryptedCookies = [];

        return $this;
    }

    /** A file of the given age in the inbox. A fresh one counts as still being uploaded. */
    private function dropPackage(string $name, int $ageSeconds): void
    {
        $path = $this->inboxDir().DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, str_repeat('x', 2048));
        touch($path, time() - $ageSeconds);
    }
}
