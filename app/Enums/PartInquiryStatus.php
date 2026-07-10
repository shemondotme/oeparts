<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PartInquiryStatus: string implements HasLabel
{
    case New        = 'new';
    case Reviewing  = 'reviewing';
    case Sourced    = 'sourced';
    case Unavailable = 'unavailable';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Reviewing => 'Reviewing',
            self::Sourced => 'Sourced',
            self::Unavailable => 'Unavailable',
        };
    }
}
