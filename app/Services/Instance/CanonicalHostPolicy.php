<?php

namespace App\Services\Instance;

/**
 * What `seo.canonical_host` becomes on a site that has just been moved in.
 *
 * The setting is the one host the site insists on (everything else is redirected to it), so copying the old value onto a new
 * address would send every visitor of the new site back to the old one.
 *
 *   live launch     the bare new host if the source site HAD a canonical host, empty if it had none (the same behaviour as before);
 *   staging launch  always empty: a copy must answer on whatever address it is given, and the old value is kept in the ledger so
 *                   that going live can put the right one back.
 *
 * Only a bare host is accepted (letters, digits, dots and hyphens): never a scheme, a port, a path or a user.
 */
final class CanonicalHostPolicy
{
    public const LIVE = 'live';

    public const STAGING = 'staging';

    /** A bare host: dot-separated labels of letters, digits and inner hyphens, at least one letter somewhere, at most 253 characters. */
    public static function isBare(string $value): bool
    {
        return strlen($value) <= 253
            && preg_match('/^(?=.*[a-z])([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i', $value) === 1;
    }

    /**
     * @param  string  $launch  `live` or `staging`
     * @param  string  $sourceCanonical  the value the package carried (may be empty)
     * @param  string  $newHost  the bare host of the new site
     *
     * @throws \InvalidArgumentException the launch mode is unknown, or a host is not bare
     */
    public function resolve(string $launch, string $sourceCanonical, string $newHost): string
    {
        if (! in_array($launch, [self::LIVE, self::STAGING], true)) {
            throw new \InvalidArgumentException("Unknown launch mode '{$launch}'.");
        }

        if ($sourceCanonical !== '' && ! self::isBare($sourceCanonical)) {
            throw new \InvalidArgumentException('The canonical host of the source site is not a bare host.');
        }

        if (! self::isBare($newHost)) {
            throw new \InvalidArgumentException('The new site address does not have a bare host.');
        }

        if ($launch === self::STAGING || $sourceCanonical === '') {
            return '';
        }

        return strtolower($newHost);
    }
}
