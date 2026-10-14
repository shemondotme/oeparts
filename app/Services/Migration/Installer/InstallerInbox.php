<?php

namespace App\Services\Migration\Installer;

use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageReader;
use App\Services\Migration\Ux\MigrationText;

/**
 * What the installer shows of the inbox. The listing itself belongs to ImportInbox (names that look like packages, no
 * symbolic links); this adds the things only a person watching an upload needs:
 *
 *  - a file that changed a moment ago is still being copied in by an FTP program and must not be chosen yet;
 *  - a finished file that is not a package (or lost its end, or was sent in text mode) says so before anyone types a passphrase;
 *  - the listing can be switched off (OE_INSTALLER_LIST_INBOX=false), after which the installer asks for the file name.
 */
final class InstallerInbox
{
    /** More files than this are listed without the header check, so a folder full of files cannot make the page slow. */
    private const PEEK_LIMIT = 25;

    public function __construct(
        private readonly ImportInbox $inbox,
        private readonly Kdf $kdf,
        private readonly MigrationText $text,
    ) {}

    public static function forApplication(): self
    {
        return app(self::class);
    }

    public function listingEnabled(): bool
    {
        return (bool) config('migration.installer.list_inbox', true);
    }

    /**
     * @return array<int, array{name: string, size: int, modified: int, stable: bool}> newest first; empty when the listing is switched off
     */
    public function list(): array
    {
        if (! $this->listingEnabled()) {
            return [];
        }

        $settle = $this->settleSeconds();
        $now = now()->timestamp;

        return array_map(
            static fn (array $file): array => $file + ['stable' => $now - $file['modified'] >= $settle],
            $this->inbox->list(),
        );
    }

    /**
     * The listing with, for every finished file, what its header says: nothing wrong (`problem` null), or the title and message
     * of the reason it cannot be a package. No passphrase is involved; only the first and the last bytes are read.
     *
     * @return array<int, array{name: string, size: int, modified: int, stable: bool, problem: array{title: string, message: string}|null}>
     */
    public function listWithProblems(): array
    {
        $files = $this->list();

        foreach ($files as $index => &$file) {
            $file['problem'] = $file['stable'] && $index < self::PEEK_LIMIT ? $this->problemOf($file['name']) : null;
        }
        unset($file);

        return $files;
    }

    /** How many finished packages wait in the inbox (the badge on step 1). */
    public function stableCount(): int
    {
        return count(array_filter($this->list(), static fn (array $file): bool => $file['stable']));
    }

    /** Whether a typed or chosen name is a package that has finished arriving. */
    public function isStable(string $name): bool
    {
        if ($this->listingEnabled()) {
            foreach ($this->list() as $file) {
                if ($file['name'] === $name) {
                    return $file['stable'];
                }
            }

            return false;
        }

        // The listing is off: judge the named file directly.
        try {
            $path = $this->inbox->resolve($name);
        } catch (\Throwable) {
            return false;
        }

        clearstatcache(true, $path);

        return now()->timestamp - (int) filemtime($path) >= $this->settleSeconds();
    }

    public function directory(): string
    {
        return $this->inbox->directory();
    }

    /** The folder as an operator types it into an FTP program: relative to the application when it lies inside it. */
    public function displayPath(): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $inbox = str_replace('\\', '/', $this->directory());

        return str_starts_with($inbox, $base) ? substr($inbox, strlen($base)) : $inbox;
    }

    private function settleSeconds(): int
    {
        return max(1, (int) config('migration.installer.stable_seconds', 30));
    }

    /** @return array{title: string, message: string}|null */
    private function problemOf(string $name): ?array
    {
        try {
            $peek = PackageReader::peek($this->inbox->resolve($name), $this->kdf);
            $code = $peek->hasTrailer ? null : MigrationErrorCode::PackageIncomplete;
        } catch (PackageException $e) {
            $code = $e->errorCode();
        } catch (\Throwable) {
            $code = MigrationErrorCode::PackageNotFound;
        }

        if ($code === null) {
            return null;
        }

        $error = $this->text->error($code, [], true);

        return ['title' => $error['title'], 'message' => $error['message']];
    }
}
