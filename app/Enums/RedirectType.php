<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RedirectType: string implements HasLabel
{
    case Permanent = '301';
    case Temporary = '302';
    /** "Removed on purpose" (HTTP 410): no destination; search engines drop the URL faster than for a 404. */
    case Gone = '410';

    public function getLabel(): string
    {
        return match ($this) {
            self::Permanent => '301 — Permanent',
            self::Temporary => '302 — Temporary',
            self::Gone => '410 — Gone (removed on purpose)',
        };
    }
}
