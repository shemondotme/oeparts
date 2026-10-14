<?php

namespace App\Services\Migration\Rewrite;

/**
 * What a stored value is, which decides what the domain rewrite may do to it.
 *
 * The first six are rewritten with the old address replaced by the new one. The rest are deliberately not:
 * a value that records what happened in the past, an email address, a script nobody should guess about, a host
 * that has a policy of its own, or a column that only looks like it could hold an address.
 */
enum RewriteKind: string
{
    /** Free text: every address of the old site in it is replaced. */
    case Plain = 'plain';

    /** One address (a link, a redirect target). */
    case Url = 'url';

    /** JSON text: replaced, and the result must still be the same JSON. */
    case Json = 'json';

    /** JSON with one text per language: handled as JSON. */
    case MultilangJson = 'multilang_json';

    /** HTML: src, href, srcset and inline styles are all covered by the same rule as plain text. */
    case Html = 'html';

    /** CSS: url(...) and @import. */
    case Css = 'css';

    /** A bare host with a policy of its own (the canonical host); never rewritten as text. */
    case Host = 'host';

    /** An email address. Never rewritten: it belongs to a mailbox, not to the site. Listed by the readiness check. */
    case Email = 'email';

    /** Scripts and similar: looked at and reported, never changed. */
    case ScanOnly = 'scan_only';

    /** History that must stay as it was written: an old order note, a gateway response, a log line, a legal document. */
    case Preserve = 'preserve';

    /** Looks like it could hold an address but cannot (a colour, a snapshot of a part number), with the reason. */
    case Ignore = 'ignore';

    /** May the rewrite change a value of this kind? */
    public function isRewritten(): bool
    {
        return match ($this) {
            self::Plain, self::Url, self::Json, self::MultilangJson, self::Html, self::Css => true,
            default => false,
        };
    }

    public function isJson(): bool
    {
        return $this === self::Json || $this === self::MultilangJson;
    }
}
