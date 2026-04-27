<?php

namespace App\Enums;

enum SectionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function badgeColor(): string
    {
        return match($this) {
            self::Draft => 'slate',
            self::Scheduled => 'blue',
            self::Published => 'green',
            self::Archived => 'gray',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Published;
    }

    public function isScheduled(): bool
    {
        return in_array($this, [self::Scheduled], true);
    }
}
