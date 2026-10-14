<?php

namespace App\Services\Migration\Import;

/** The result of comparing the migrations of a package with those of the site that would receive it. */
final class LedgerComparison
{
    /**
     * @param  array<int, string>  $missingInTarget  migrations the package's site ran and this site has not (this site is OLDER)
     * @param  array<int, string>  $missingInPackage  migrations this site ran and the package's site has not (this site is NEWER)
     */
    public function __construct(
        public readonly int $packageCount,
        public readonly int $targetCount,
        public readonly array $missingInTarget,
        public readonly array $missingInPackage,
        public readonly ?string $packageVersion,
        public readonly ?string $targetVersion,
    ) {}

    /** The package carries no migration list at all (an export from before the list existed, or a hand-made file). */
    public function packageLedgerIsEmpty(): bool
    {
        return $this->packageCount === 0;
    }

    /** Both sites ran exactly the same migrations, and there are some. */
    public function identical(): bool
    {
        return $this->packageCount > 0 && $this->missingInTarget === [] && $this->missingInPackage === [];
    }

    public function targetIsOlder(): bool
    {
        return $this->missingInTarget !== [];
    }

    public function targetIsNewer(): bool
    {
        return $this->missingInPackage !== [] && $this->missingInTarget === [];
    }

    public function versionsDiffer(): bool
    {
        return $this->packageVersion !== null && $this->targetVersion !== null && $this->packageVersion !== $this->targetVersion;
    }

    /** A different MAJOR version, even with the same migrations: worth a deliberate "yes, I know". */
    public function isMajorJump(): bool
    {
        if (! $this->versionsDiffer()) {
            return false;
        }

        return (int) explode('.', (string) $this->packageVersion)[0] !== (int) explode('.', (string) $this->targetVersion)[0];
    }

    /** Is the package from a NEWER release than this site? Compared as versions, not as text. */
    public function packageIsNewerRelease(): bool
    {
        return $this->versionsDiffer() && version_compare((string) $this->packageVersion, (string) $this->targetVersion, '>');
    }

    /** @return array<string, mixed> short enough for a state file and a report: counts and the first few names */
    public function toArray(int $namesShown = 8): array
    {
        return [
            'package_migrations' => $this->packageCount, 'target_migrations' => $this->targetCount,
            'missing_in_target' => array_slice($this->missingInTarget, 0, $namesShown), 'missing_in_target_count' => count($this->missingInTarget),
            'missing_in_package' => array_slice($this->missingInPackage, 0, $namesShown), 'missing_in_package_count' => count($this->missingInPackage),
            'package_version' => $this->packageVersion, 'target_version' => $this->targetVersion,
        ];
    }
}
