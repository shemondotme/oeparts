<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactStatus: string implements HasLabel
{
    case Unread   = 'unread';
    case Read     = 'read';
    case Resolved = 'resolved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unread => 'Unread',
            self::Read => 'Read',
            self::Resolved => 'Resolved',
        };
    }
}
