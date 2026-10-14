<?php

namespace App\Services\Migration\Import;

/** What the site an import would replace holds right now. */
enum TargetClass: string
{
    /** Nothing but what a fresh install creates: no row outside the core tables. */
    case Empty = 'empty';

    /** Content (a catalog, pages, menus, demo data) but no business activity: nobody has ordered, paid or signed up. */
    case Fresh = 'fresh';

    /** A site people have used: orders, payments, customers, invoices or messages exist (or it is simply very large). */
    case Populated = 'populated';

    /** The installer door only ever runs on a site that nobody has used. */
    public function installerMayReplace(): bool
    {
        return $this !== self::Populated;
    }
}
