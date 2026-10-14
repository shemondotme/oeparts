<?php

namespace App\Services\Migration\Rewrite;

/** What the domain rewrite does with one stored setting. */
enum SettingAction: string
{
    /** The site address itself: set to the new address. */
    case Set = 'set';

    /** The canonical host: decided by CanonicalHostPolicy (a launch keeps it, staging empties it), never by text replacement. */
    case CanonicalHost = 'canonical_host';

    /** A value that is only valid for the old site and must be emptied (the Search Console property). */
    case Clear = 'clear';

    /** An address: replaced like text. */
    case Url = 'url';

    /** Custom CSS: replaced like text. */
    case Css = 'css';

    /** Scripts: reported, never changed. */
    case ScanOnly = 'scan_only';

    /** An email address: never changed, listed by readiness so the owner can check each. */
    case Email = 'email';

    /** Looks like a host or address but is not this site's (an SMTP server, a port). */
    case Ignore = 'ignore';

    public function changesValue(): bool
    {
        return match ($this) {
            self::Set, self::Clear, self::Url, self::Css => true,
            default => false,
        };
    }
}
