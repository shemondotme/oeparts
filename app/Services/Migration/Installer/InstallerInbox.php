<?php

namespace App\Services\Migration\Installer;

use App\Services\Migration\Import\ImportInbox;

/**
 * What the installer shows of the inbox. The listing itself belongs to ImportInbox (names that look like packages, no
 * symbolic links); this adds the two things only a person watching an upload needs:
 *
 *  - a file that changed a moment ago is still being copied in by an FTP program and must not be chosen yet;
 *  - the listing can be switched off (OE_INSTALLER_LIST_INBOX=false), after which the installer asks for the file name.
 */
final class InstallerInbox
{
    public function __construct(private readonly ImportInbox $inbox) {}

    public static function forApplication(): self
    {
        return new self(app(ImportInbox::class));
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

        $settle = max(1, (int) config('migration.installer.stable_seconds', 30));
        $now = now()->timestamp;

        return array_map(
            static fn (array $file): array => $file + ['stable' => $now - $file['modified'] >= $settle],
            $this->inbox->list(),
        );
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

        return now()->timestamp - (int) filemtime($path) >= max(1, (int) config('migration.installer.stable_seconds', 30));
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
}
