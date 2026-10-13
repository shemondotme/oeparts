<?php

namespace App\Security;

/** The answer of PackagePathPolicy: allowed, or the reason it was refused. */
final class PathDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $path,
        public readonly ?PathRejection $reason,
    ) {}

    public static function allow(string $path): self
    {
        return new self(true, $path, null);
    }

    public static function reject(PathRejection $reason): self
    {
        return new self(false, null, $reason);
    }
}
